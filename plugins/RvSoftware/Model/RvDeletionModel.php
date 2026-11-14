<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvDeletionModel extends RvBaseModel
{
    public function remove($kind, $id, callable $operation)
    {
        if (! in_array($kind,['projects','tasks','subtasks'],true)) { throw new \LogicException('Invalid deletion kind'); }
        $pdo=$this->db->getConnection(); $own=!$pdo->inTransaction();
        if ($own) { $pdo->beginTransaction(); }
        try {
            if (DB_DRIVER==='sqlite') { $this->sql('UPDATE '.$kind.' SET id=id WHERE id=?',[$id]); }
            $suffix=DB_DRIVER==='mysql' ? ' FOR UPDATE' : '';
            $row=$this->sql('SELECT id FROM '.$kind.' WHERE id=?'.$suffix,[$id])->fetchColumn();
            if (! $row) { if ($own) { $pdo->commit(); } return false; }
            if (DB_DRIVER==='mysql') { $this->lockDescendants($kind,$id); }
            $this->assertNoHistory($kind,$id);
            $result=$operation();
            if ($own) { $pdo->commit(); }
            return $result;
        } catch (\Throwable $error) { if ($own && $pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }

    private function lockDescendants($kind,$id)
    {
        if ($kind==='projects') {
            $this->sql('SELECT id FROM tasks WHERE project_id=? ORDER BY id FOR UPDATE',[$id])->fetchAll();
            $this->sql('SELECT s.id FROM subtasks s JOIN tasks t ON t.id=s.task_id WHERE t.project_id=? ORDER BY s.id FOR UPDATE',[$id])->fetchAll();
        } elseif ($kind==='tasks') {
            $this->sql('SELECT id FROM subtasks WHERE task_id=? ORDER BY id FOR UPDATE',[$id])->fetchAll();
        }
    }

    private function assertNoHistory($kind, $id)
    {
        if ($kind==='projects' && $this->sql('SELECT 1 FROM rv_project_clients WHERE project_id=?',[$id])->fetchColumn()) { throw new \DomainException('HISTORY_CONFLICT: el proyecto conserva historia comercial; ciérralo en lugar de eliminarlo.'); }
        $condition=['projects'=>'t.project_id=?','tasks'=>'t.id=?','subtasks'=>'s.id=?'][$kind];
        $taskTotal=$kind==='subtasks' ? '' : ' OR t.time_spent<>0';
        $history=$this->sql('SELECT 1 FROM tasks t LEFT JOIN subtasks s ON s.task_id=t.id LEFT JOIN subtask_time_tracking h ON h.subtask_id=s.id WHERE '.$condition.' AND (h.id IS NOT NULL OR s.time_spent<>0'.$taskTotal.') LIMIT 1',[$id])->fetchColumn();
        if ($history) { throw new \DomainException('HISTORY_CONFLICT: el registro tiene horas asociadas y no se puede eliminar.'); }
    }
}
