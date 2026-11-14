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
}
