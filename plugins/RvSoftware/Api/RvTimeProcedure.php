<?php

namespace Kanboard\Plugin\RvSoftware\Api;

class RvTimeProcedure extends \Kanboard\Api\Procedure\BaseProcedure
{
    public function rvTimeReport($from,$until,$filters = array())
    {
        return $this->rvReportModel->report($from,$until,$filters);
    }
    public function rvAdjustBag($bag_id,$seconds,$reason,$operation_key)
    {
        return $this->rvLedgerModel->adjust($bag_id,$seconds,$reason,$operation_key);
    }

    public function rvReconcile($project_id)
    {
        return $this->rvLedgerModel->reconcile($project_id);
    }
    public function rvAssignClient($project_id, $name, $operation_key, $client_id = 0)
    {
        return $this->rvBagModel->assignClient($project_id,$name,$client_id,$operation_key);
    }

    public function rvCreateBag($project_id,$name,$seconds,$operation_key,$valid_from = null,$valid_until = null)
    {
        return $this->rvBagModel->createBag($project_id,$name,$seconds,$valid_from,$valid_until,$operation_key);
    }

    public function rvGetBags($project_id)
    {
        return $this->rvBagModel->bags($project_id);
    }

    public function rvGetBagEntries($bag_id)
    {
        return $this->rvBagModel->entries($bag_id);
    }

    public function rvExtendBag($bag_id,$seconds,$reason,$operation_key)
    {
        return $this->rvBagModel->amend($bag_id,$seconds,$reason,$operation_key);
    }

    public function rvArchiveBag($bag_id,$reason,$operation_key)
    {
        return $this->rvBagModel->amend($bag_id,0,$reason,$operation_key,true);
    }
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

    public function rvReviewTime($session_id, $billable, $approve, $reason, $operation_key, $bag_id = null)
    {
        return $this->rvTimeModel->review($session_id, $billable, $approve, $reason, $operation_key, $bag_id);
    }

    public function rvRectifyTime($session_id, $start, $end, $billable, $note, $reason, $operation_key, $bag_id = null)
    {
        return $this->rvTimeModel->rectify($session_id, $start, $end, $billable, $note, $reason, $operation_key, $bag_id);
    }

    public function rvGetTimeAudit($session_id)
    {
        return $this->rvTimeModel->history($session_id);
    }
}
