<?php

namespace Kanboard\Plugin\RvSoftware\Model;

class RvTrackingModel extends \Kanboard\Model\SubtaskTimeTrackingModel
{
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
