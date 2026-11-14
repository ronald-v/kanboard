<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvTaskFinderModel extends \Kanboard\Model\TaskFinderModel
{
    public function getByReference($project_id, $reference)
    {
        $row = parent::getByReference($project_id, $reference);
        return $row ? $this->rvPrivacyModel->task($row) : $row;
    }

    public function getDetails($task_id)
    {
        $row = parent::getDetails($task_id);
        return $row ? $this->rvPrivacyModel->task($row) : $row;
    }
    public function getExtendedQuery()
    {
        return $this->rvPrivacyModel->decorate(parent::getExtendedQuery(), 'task');
    }

    public function getProjectUserOverviewQuery(array $project_ids, $is_active)
    {
        return $this->rvPrivacyModel->decorate(parent::getProjectUserOverviewQuery($project_ids, $is_active), 'task');
    }

    public function getAll($project_id, $status_id = \Kanboard\Model\TaskModel::STATUS_OPEN)
    {
        $query = $this->db->table(\Kanboard\Model\TaskModel::TABLE)
            ->eq('tasks.project_id', $project_id)->eq('tasks.is_active', $status_id)->asc('tasks.id');
        return $this->rvPrivacyModel->decorate($query, 'task')->findAll();
    }

    public function getById($task_id)
    {
        $row = parent::getById($task_id);
        return $row ? $this->rvPrivacyModel->task($row) : $row;
    }
}
