<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvTaskProjectMoveModel extends \Kanboard\Model\TaskProjectMoveModel
{
    public function moveToProject($task_id, $project_id, $swimlane_id = null, $column_id = null, $category_id = null, $owner_id = null)
    {
        return $this->rvTimeModel->atomic(0, function () use ($task_id,$project_id,$swimlane_id,$column_id,$category_id,$owner_id) {
        $current = $this->db->table('tasks')->eq('id', $task_id)->findOneColumn('project_id');
        if ((int) $current !== (int) $project_id && $this->rvTimeModel->sql("SELECT COUNT(*) FROM rv_time_meta m JOIN subtask_time_tracking t ON t.id=m.session_id JOIN subtasks s ON s.id=t.subtask_id WHERE s.task_id=? AND m.status='approved'", array($task_id))->fetchColumn()) {
            return false;
        }
        return parent::moveToProject($task_id, $project_id, $swimlane_id, $column_id, $category_id, $owner_id);
        }, $task_id);
    }
}
