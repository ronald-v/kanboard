<?php

namespace Kanboard\Plugin\RvSoftware\Model;

use Kanboard\Core\Base;
use Kanboard\Core\Security\Role;
use DomainException;
use Throwable;
use PDOException;

class RvBaseModel extends Base
{
    public function sql($sql, array $params = array())
    {
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    private function lockRow($table, $column, $id)
    {
        if (DB_DRIVER === 'mysql') {
            $this->sql("INSERT INTO $table($column,revision) VALUES (?,0) ON DUPLICATE KEY UPDATE revision=revision", array($id));
        } else {
            $this->sql("INSERT OR IGNORE INTO $table($column,revision) VALUES (?,0)", array($id));
        }
        $this->sql("UPDATE $table SET revision=revision+1 WHERE $column=?", array($id));
    }

    public function atomic($userId, callable $operation, $taskId = 0, $projectId = 0, $retrySafe = true)
    {
        $pdo = $this->db->getConnection();
        $own = ! $pdo->inTransaction();
        for ($attempt = 1; ; $attempt++) {
            $savepoint = 'rv_'.bin2hex(random_bytes(6));
            if ($own) { $pdo->beginTransaction(); } else { $pdo->exec('SAVEPOINT '.$savepoint); }
            try {
                if ($userId > 0) { $this->lockRow('rv_time_locks', 'user_id', $userId); }
                if ($taskId > 0) {
                    if (DB_DRIVER === 'mysql') {
                        // The mutex FK otherwise takes a shared parent-row lock
                        // while waiting for the mutex. A concurrent projection
                        // update then needs that same task exclusively: lock the
                        // parent first to avoid that lock upgrade cycle.
                        $this->sql('SELECT id FROM tasks WHERE id=? FOR UPDATE',array($taskId));
                    }
                    $this->lockRow('rv_task_time_locks', 'task_id', $taskId);
                }
                if ($projectId > 0) { $this->lockRow('rv_project_locks', 'project_id', $projectId); }
                $result = $operation();
                if ($own) { $pdo->commit(); } else { $pdo->exec('RELEASE SAVEPOINT '.$savepoint); }
                return $result;
            } catch (Throwable $error) {
                if ($own && $pdo->inTransaction()) { $pdo->rollBack(); }
                elseif (! $own && $pdo->inTransaction()) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
                    $pdo->exec('RELEASE SAVEPOINT '.$savepoint);
                }
                // Retry only a transaction we own, after complete rollback, and
                // only DB-only operations. Nested callers and operations with
                // native event/queue effects must be retried by their caller.
                if ($own && $retrySafe && $attempt < 3 && $error instanceof PDOException && ((string)$error->getCode() === '40001' || ($error->errorInfo[1] ?? null) === 1213)) {
                    usleep($attempt * 30000);
                    continue;
                }
                throw $error;
            }
        }
    }

    public function actor()
    {
        $id = $this->userSession->getId();
        if ($id <= 0) { throw new DomainException('Se requiere una sesión de usuario.'); }
        return $id;
    }

    public function role($projectId, $userId)
    {
        $user = $this->userModel->getById($userId);
        return $user && $user['role'] === Role::APP_ADMIN ? Role::PROJECT_MANAGER : $this->projectUserRoleModel->getUserRole($projectId, $userId);
    }

    public function writableSubtask($subtaskId, $userId)
    {
        if (filter_var($subtaskId, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false || filter_var($userId, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
            throw new DomainException('La subtarea o el usuario no son válidos.');
        }
        $subtask = $this->subtaskModel->getById((int) $subtaskId);
        if (! $subtask || ! in_array($this->role($this->subtaskModel->getProjectId($subtaskId), $userId), array(Role::PROJECT_MEMBER, Role::PROJECT_MANAGER), true)) {
            throw new DomainException('No tienes permiso para registrar tiempo en esta subtarea.');
        }
        return $subtask;
    }

    public function text($value, $maxBytes = 8000)
    {
        if (! is_string($value) || strlen($value) > $maxBytes || ! mb_check_encoding($value, 'UTF-8')) {
            throw new DomainException('El texto no es válido o es demasiado largo.');
        }
        return trim($value);
    }

    public function operationKey($kind, $userId, $key)
    {
        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_.:-]{8,64}$/D', $key)) {
            throw new DomainException('La clave de operación no es válida.');
        }
        return $kind.':'.$userId.':'.$key;
    }
}
