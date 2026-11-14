<?php

namespace Kanboard\Plugin\RvSoftware\Api;

class RvTimeProcedure extends \Kanboard\Api\Procedure\BaseProcedure
{
    public function rvCreateTime($subtask_id, $start, $end, $note, $operation_key)
    {
        return $this->rvTimeModel->manual($subtask_id, $start, $end, $note, $operation_key);
    }

    public function rvCorrectTime($session_id, $start, $end, $note, $reason, $operation_key)
    {
        return $this->rvTimeModel->correct($session_id, $start, $end, $note, $reason, $operation_key);
    }

    public function rvGetMyTime()
    {
        return $this->rvTimeModel->mine();
    }

    public function rvGetVisibleTime()
    {
        return $this->rvTimeModel->visible();
    }

    public function rvGetTime($session_id)
    {
        return $this->rvTimeModel->readable($session_id);
    }

    public function rvReviewTime($session_id, $billable, $approve, $reason, $operation_key)
    {
        return $this->rvTimeModel->review($session_id, $billable, $approve, $reason, $operation_key);
    }

    public function rvRectifyTime($session_id, $start, $end, $billable, $note, $reason, $operation_key)
    {
        return $this->rvTimeModel->rectify($session_id, $start, $end, $billable, $note, $reason, $operation_key);
    }

    public function rvGetTimeAudit($session_id)
    {
        return $this->rvTimeModel->history($session_id);
    }
}
