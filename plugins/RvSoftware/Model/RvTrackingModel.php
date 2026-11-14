<?php

namespace Kanboard\Plugin\RvSoftware\Model;

class RvTrackingModel extends \Kanboard\Model\SubtaskTimeTrackingModel
{
    public function getUserQuery($user_id)
    {
        $query = parent::getUserQuery($user_id);
        if ($this->userSession->isLogged() && ! $this->userSession->isAdmin()) {
            $actor = $this->userSession->getId();
            $ids = (int) $user_id === $actor ? $this->projectPermissionModel->getProjectIds($actor) : $this->rvPrivacyModel->leaderProjectIds();
            $query->in('tasks.project_id', $ids ?: array(-1));
        }
        return $query;
    }

    public function getTaskQuery($task_id)
    {
        $query = parent::getTaskQuery($task_id);
        $projectId = $this->db->table('tasks')->eq('id', $task_id)->findOneColumn('project_id');
        if (! $this->rvPrivacyModel->lead($projectId)) { $query->eq('subtask_time_tracking.user_id', $this->userSession->getId()); }
        return $query;
    }

    public function getUserTimesheet($user_id)
    {
        return $this->getUserQuery($user_id)->findAll();
    }

    public function hasTimer($subtask_id, $user_id)
    {
        $projectId = $this->subtaskModel->getProjectId($subtask_id);
        if ($this->userSession->isLogged() && (int) $user_id !== $this->userSession->getId() && ! $this->rvPrivacyModel->lead($projectId)) { return false; }
        return parent::hasTimer($subtask_id, $user_id);
    }

    public function getTimeSpent($subtask_id, $user_id)
    {
        $projectId = $this->subtaskModel->getProjectId($subtask_id);
        if ($this->userSession->isLogged() && (int) $user_id !== $this->userSession->getId() && ! $this->rvPrivacyModel->lead($projectId)) { return 0; }
        return parent::getTimeSpent($subtask_id, $user_id);
    }

    public function updateTaskTimeTracking($task_id)
    {
        return $this->rvTimeModel->refreshTask($task_id);
    }

    public function logStartTime($subtask_id, $user_id)
    {
        try { return $this->rvTimeModel->start($subtask_id, (int) $user_id) > 0; }
        catch (\DomainException $error) { return false; }
    }

    public function logEndTime($subtask_id, $user_id)
    {
        try { return $this->rvTimeModel->stop($subtask_id, (int) $user_id); }
        catch (\DomainException $error) { return false; }
    }
}
