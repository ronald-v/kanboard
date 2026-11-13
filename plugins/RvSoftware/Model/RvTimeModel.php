<?php

namespace Kanboard\Plugin\RvSoftware\Model;

use DomainException;
use PDO;

class RvTimeModel extends RvBaseModel
{
    public function session($id)
    {
        return $this->sql('SELECT t.*, m.duration_seconds, m.source, m.note, m.version,m.status,m.billable,m.approved_by,m.approved_at,m.approved_project_id,m.rectified_at FROM subtask_time_tracking t JOIN rv_time_meta m ON m.session_id=t.id WHERE t.id=?', array($id))->fetch(PDO::FETCH_ASSOC);
    }

    public function audit($id, $action, $before, $after, $reason, $key, $hash = '')
    {
        $this->sql('INSERT INTO rv_time_audit(session_id,actor_id,action,reason,before_json,after_json,created_at,operation_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?)',
            array($id, $this->userSession->getId(), $action, $reason, json_encode($before), json_encode($after), time(), $key, $hash));
    }

    private function period($start, $end)
    {
        if (filter_var($start, FILTER_VALIDATE_INT) === false || filter_var($end, FILTER_VALIDATE_INT) === false || $start <= 0 || $end < $start || $end > time()) {
            throw new DomainException('Revisa las fechas: el fin debe ser posterior al inicio y no puede estar en el futuro.');
        }
        if ($end - $start > 2147483647) { throw new DomainException('La duración no es válida.'); }
    }

    private function meta($id, $seconds, $source, $note, $key, $hash)
    {
        $this->sql('INSERT INTO rv_time_meta(session_id,duration_seconds,source,note,operation_key,request_hash,version) VALUES (?,?,?,?,?,?,1)', array($id, $seconds, $source, $note, $key, $hash));
    }

    private function projectDelta($subtaskId, $delta)
    {
        // Native rounded hours remain a projection. Exact seconds never use it.
        $legacy = $this->sql("SELECT hours FROM rv_time_legacy WHERE entity_type='subtask' AND entity_id=?", array($subtaskId))->fetchColumn();
        $hours = $this->sql('SELECT COALESCE(SUM(t.time_spent),0) FROM subtask_time_tracking t JOIN rv_time_meta m ON m.session_id=t.id WHERE t.subtask_id=?', array($subtaskId))->fetchColumn();
        $this->sql('UPDATE subtasks SET time_spent=? WHERE id=?', array((float) $legacy + (float) $hours, $subtaskId));
        $subtask = $this->subtaskModel->getById($subtaskId);
        if (! $this->subtaskTimeTrackingModel->updateTaskTimeTracking($subtask['task_id'])) {
            throw new DomainException('No se pudo actualizar el total de la tarea.');
        }
    }

    public function refreshTask($taskId)
    {
        return $this->atomic(0, function () use ($taskId) {
            $legacy = $this->sql("SELECT hours FROM rv_time_legacy WHERE entity_type='task' AND entity_id=?", array($taskId))->fetchColumn();
            $hours = $this->sql('SELECT COALESCE(SUM(t.time_spent),0) FROM subtask_time_tracking t JOIN rv_time_meta m ON m.session_id=t.id JOIN subtasks s ON s.id=t.subtask_id WHERE s.task_id=?', array($taskId))->fetchColumn();
            $values = $this->subtaskTimeTrackingModel->calculateSubtaskTime($taskId);
            $values['time_spent'] = (float) $legacy + (float) $hours;
            return $this->db->table('tasks')->eq('id', $taskId)->update($values);
        }, $taskId);
    }

    public function manual($subtaskId, $start, $end, $note, $key)
    {
        $userId = $this->actor();
        $subtask = $this->writableSubtask($subtaskId, $userId);
        $this->period($start, $end);
        $note = $this->text($note);
        $key = $this->operationKey('manual', $userId, $key);
        $hash = hash('sha256', json_encode(array((int) $subtaskId, (int) $start, (int) $end, $note)));
        return $this->atomic($userId, function () use ($subtaskId, $start, $end, $note, $key, $hash, $userId) {
            $existing = $this->db->table('rv_time_meta')->eq('operation_key', $key)->findOne();
            if ($existing) {
                if (! hash_equals($existing['request_hash'], $hash)) { throw new DomainException('La clave ya se usó con otros datos.'); }
                return (int) $existing['session_id'];
            }
            $seconds = (int) $end - (int) $start;
            $hours = round($seconds / 3600, 2);
            $this->sql('INSERT INTO subtask_time_tracking(subtask_id,user_id,`start`,`end`,time_spent) VALUES (?,?,?,?,?)', array($subtaskId, $userId, $start, $end, $hours));
            $id = (int) $this->db->getConnection()->lastInsertId();
            $this->meta($id, $seconds, 'manual', $note, $key, $hash);
            $this->projectDelta($subtaskId, $hours);
            $this->audit($id, 'manual', array(), $this->session($id), 'Registro manual', $key, $hash);
            return $id;
        }, $subtask['task_id']);
    }

    public function start($subtaskId, $userId)
    {
        if ($this->userSession->isLogged() && $this->actor() !== (int) $userId) { throw new DomainException('Solo puedes iniciar tu temporizador.'); }
        if (! $this->userSession->isLogged() && session_get('scope') !== 'API') { throw new DomainException('Se requiere autenticación.'); }
        $subtask = $this->writableSubtask($subtaskId, $userId);
        return $this->atomic($userId, function () use ($subtaskId, $userId) {
            $open = $this->db->table('subtask_time_tracking')->eq('user_id', $userId)->eq('end', 0)->findAll();
            if ($open) {
                if (count($open) === 1 && (int) $open[0]['subtask_id'] === (int) $subtaskId) { return (int) $open[0]['id']; }
                throw new DomainException('Detén tu temporizador activo antes de iniciar otro.');
            }
            $this->sql('INSERT INTO subtask_time_tracking(subtask_id,user_id,`start`,`end`,time_spent) VALUES (?,?,?,0,0)', array($subtaskId, $userId, time()));
            $id = (int) $this->db->getConnection()->lastInsertId();
            $key = 'timer:'.$id;
            $this->meta($id, null, 'timer', '', $key, '');
            $this->audit($id, 'start', array(), $this->session($id), 'Inicio de temporizador', $key);
            return $id;
        }, $subtask['task_id']);
    }

    public function stop($subtaskId, $userId)
    {
        if ($this->userSession->isLogged() && $this->actor() !== (int) $userId) { throw new DomainException('Solo puedes detener tu temporizador.'); }
        if (! $this->userSession->isLogged() && session_get('scope') !== 'API') { throw new DomainException('Se requiere autenticación.'); }
        $subtask = $this->writableSubtask($subtaskId, $userId);
        return $this->atomic($userId, function () use ($subtaskId, $userId) {
            $row = $this->db->table('subtask_time_tracking')->eq('subtask_id', $subtaskId)->eq('user_id', $userId)->eq('end', 0)->findOne();
            if (! $row) {
                if ($this->db->table('subtask_time_tracking')->eq('user_id', $userId)->eq('end', 0)->exists()) {
                    throw new DomainException('El temporizador activo pertenece a otra subtarea.');
                }
                return true; // Retry of an already-closed operation has no effect.
            }
            $id = (int) $row['id'];
            $end = max(time(), (int) $row['start']);
            $seconds = $end - (int) $row['start'];
            $hours = round($seconds / 3600, 2);
            if (! $this->db->table('rv_time_meta')->eq('session_id', $id)->exists()) {
                $this->meta($id, null, 'legacy_timer', '', 'adopt:'.$id, '');
            }
            $before = $this->session($id);
            $this->sql('UPDATE subtask_time_tracking SET `end`=?,time_spent=? WHERE id=? AND `end`=0', array($end, $hours, $id));
            $this->sql('UPDATE rv_time_meta SET duration_seconds=?,version=version+1 WHERE session_id=?', array($seconds, $id));
            $this->projectDelta($subtaskId, $hours);
            $this->audit($id, 'stop', $before, $this->session($id), 'Cierre de temporizador', 'stop:'.$id);
            return true;
        }, $subtask['task_id']);
    }

    public function correct($id, $start, $end, $note, $reason, $key)
    {
        $userId = $this->actor();
        $this->period($start, $end);
        $note = $this->text($note);
        $reason = $this->text($reason);
        if (! $reason) { throw new DomainException('Indica el motivo de la corrección.'); }
        $key = $this->operationKey('correct', $userId, $key);
        $original = $this->session($id);
        $taskId = $original ? $this->subtaskModel->getById($original['subtask_id'])['task_id'] : 0;
        $hash = hash('sha256', json_encode(array((int) $id, (int) $start, (int) $end, (string) $note, (string) $reason)));
        return $this->atomic($userId, function () use ($id, $start, $end, $note, $reason, $key, $hash, $userId) {
            $done = $this->db->table('rv_time_audit')->eq('operation_key', $key)->findOne();
            if ($done) {
                if (! hash_equals($done['request_hash'], $hash)) { throw new DomainException('La clave ya se usó con otros datos.'); }
                return (int) $done['session_id'];
            }
            $before = $this->session($id);
            if (! $before || (int) $before['user_id'] !== $userId || (int) $before['end'] === 0) { throw new DomainException('Solo puedes corregir tus sesiones cerradas.'); }
            if ($before['status'] !== 'pending') { throw new DomainException('Una sesión aprobada requiere rectificación por líder o administrador.'); }
            $this->writableSubtask($before['subtask_id'], $userId);
            $seconds = (int) $end - (int) $start;
            $hours = round($seconds / 3600, 2);
            $this->sql('UPDATE subtask_time_tracking SET `start`=?,`end`=?,time_spent=? WHERE id=?', array($start, $end, $hours, $id));
            $this->sql('UPDATE rv_time_meta SET duration_seconds=?,note=?,version=version+1 WHERE session_id=?', array($seconds, (string) $note, $id));
            $this->projectDelta($before['subtask_id'], $hours - (float) $before['time_spent']);
            $this->audit($id, 'correct', $before, $this->session($id), trim($reason), $key, $hash);
            return (int) $id;
        }, $taskId);
    }

    public function availableSubtasks()
    {
        $userId = $this->actor();
        $projects = $this->userSession->isAdmin() ? $this->db->table('projects')->findAll() : $this->db->table('projects')->in('id', $this->projectPermissionModel->getProjectIds($userId) ?: array(-1))->findAll();
        $ids = array();
        foreach ($projects as $project) {
            if (in_array($this->role($project['id'], $userId), array('project-member', 'project-manager'), true)) { $ids[] = $project['id']; }
        }
        if (! $ids) { return array(); }
        return $this->db->table('subtasks')->columns('subtasks.*', 'tasks.title AS task_title', 'projects.name AS project_name')
            ->join('tasks', 'id', 'task_id')->join('projects', 'id', 'project_id', 'tasks')->in('tasks.project_id', $ids)->asc('tasks.title')->findAll();
    }

    public function mine()
    {
        return $this->visible(true);
    }

    public function visible($onlyMine = false)
    {
        $actor = $this->actor();
        $where = array();
        $params = array();
        if ($onlyMine) { $where[] = 't.user_id=?'; $params[] = $actor; }
        if (! $this->userSession->isAdmin()) {
            $ids = $this->projectPermissionModel->getProjectIds($actor);
            if (! $ids) { return array(); }
            $where[] = 'k.project_id IN ('.implode(',', array_fill(0, count($ids), '?')).')';
            $params = array_merge($params, $ids);
            $leaders = $this->rvPrivacyModel->leaderProjectIds();
            if (! $onlyMine) {
                $where[] = '(t.user_id=? OR k.project_id IN ('.implode(',', array_fill(0, max(1, count($leaders)), '?')).'))';
                $params[] = $actor;
                $params = array_merge($params, $leaders ?: array(-1));
            }
        }
        return $this->sql('SELECT t.*,m.duration_seconds,m.note,m.source,m.status,m.billable,m.approved_by,m.approved_at,m.rectified_at,s.task_id,s.title AS subtask_title,k.title AS task_title,k.project_id FROM subtask_time_tracking t JOIN rv_time_meta m ON m.session_id=t.id JOIN subtasks s ON s.id=t.subtask_id JOIN tasks k ON k.id=s.task_id'.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY t.id DESC', $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function readable($id, $leader = false)
    {
        $actor = $this->actor();
        $row = $this->session($id);
        $projectId = $row ? $this->subtaskModel->getProjectId($row['subtask_id']) : 0;
        $role = $projectId ? $this->role($projectId, $actor) : '';
        if (! $row || ! $role || ($leader && $role !== 'project-manager') || (! $leader && $role !== 'project-manager' && (int) $row['user_id'] !== $actor)) {
            throw new DomainException('No tienes acceso a esta sesión.');
        }
        return $row;
    }

    public function review($id, $billable, $approve, $reason, $key)
    {
        $actor = $this->actor();
        $row = $this->readable($id, true);
        if (! in_array($billable, array(true,false,0,1), true) || ! in_array($approve, array(true,false,0,1), true)) { throw new DomainException('La clasificación no es válida.'); }
        $reason = $this->text($reason);
        $key = $this->operationKey('review', $actor, $key);
        $hash = hash('sha256', json_encode(array((int) $id,(int) $billable,(bool) $approve,$reason)));
        $taskId = $this->subtaskModel->getById($row['subtask_id'])['task_id'];
        return $this->atomic($row['user_id'], function () use ($id,$billable,$approve,$reason,$key,$hash,$actor) {
            $before = $this->readable($id, true);
            $receipt = $this->db->table('rv_time_audit')->eq('operation_key', $key)->findOne();
            if ($receipt) {
                if (! hash_equals($receipt['request_hash'], $hash)) { throw new DomainException('La clave ya se usó con otros datos.'); }
                return (int) $receipt['session_id'];
            }
            if ($before['status'] !== 'pending' || (int) $before['end'] === 0) { throw new DomainException('Solo se revisan sesiones cerradas pendientes.'); }
            $projectId = $this->subtaskModel->getProjectId($before['subtask_id']);
            $this->sql('UPDATE rv_time_meta SET billable=?,status=?,approved_by=?,approved_at=?,approved_project_id=?,version=version+1 WHERE session_id=?', array((int) $billable, $approve ? 'approved' : 'pending', $approve ? $actor : null, $approve ? time() : null, $approve ? $projectId : null, $id));
            $this->audit($id, $approve ? 'approve' : 'classify', $before, $this->session($id), $reason ?: 'Revisión de trabajo', $key, $hash);
            return (int) $id;
        }, $taskId);
    }

    public function rectify($id, $start, $end, $billable, $note, $reason, $key)
    {
        $actor = $this->actor();
        $row = $this->readable($id, true);
        $this->period($start, $end);
        $note = $this->text($note);
        $reason = $this->text($reason);
        if (! $reason || ! in_array($billable, array(true,false,0,1), true)) { throw new DomainException('Indica clasificación y motivo de rectificación.'); }
        $key = $this->operationKey('rectify', $actor, $key);
        $hash = hash('sha256', json_encode(array((int) $id,(int) $start,(int) $end,(int) $billable,$note,$reason)));
        $taskId = $this->subtaskModel->getById($row['subtask_id'])['task_id'];
        return $this->atomic($row['user_id'], function () use ($id,$start,$end,$billable,$note,$reason,$key,$hash) {
            $before = $this->readable($id, true);
            $receipt = $this->db->table('rv_time_audit')->eq('operation_key', $key)->findOne();
            if ($receipt) {
                if (! hash_equals($receipt['request_hash'], $hash)) { throw new DomainException('La clave ya se usó con otros datos.'); }
                return (int) $receipt['session_id'];
            }
            if ($before['status'] !== 'approved') { throw new DomainException('La sesión aún no está aprobada.'); }
            $seconds = (int) $end - (int) $start;
            $hours = round($seconds / 3600, 2);
            $this->sql('UPDATE subtask_time_tracking SET `start`=?,`end`=?,time_spent=? WHERE id=?', array($start,$end,$hours,$id));
            $this->sql('UPDATE rv_time_meta SET duration_seconds=?,billable=?,note=?,rectified_at=?,version=version+1 WHERE session_id=?', array($seconds,(int) $billable,$note,time(),$id));
            $this->projectDelta($before['subtask_id'], $hours - (float) $before['time_spent']);
            $this->audit($id, 'rectify', $before, $this->session($id), $reason, $key, $hash);
            return (int) $id;
        }, $taskId);
    }

    public function history($id)
    {
        $this->readable($id);
        return $this->db->table('rv_time_audit')->eq('session_id', $id)->asc('id')->findAll();
    }
}
