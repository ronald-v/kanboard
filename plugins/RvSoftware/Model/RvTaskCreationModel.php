<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvTaskCreationModel extends \Kanboard\Model\TaskCreationModel
{
    public function create(array $values)
    {
        if (session_get('scope')==='API' && ! $this->userSession->isAdmin() && ! empty($values['creator_id']) && (int)$values['creator_id']!==$this->userSession->getId()) { return 0; }
        return ! empty($values['time_spent']) ? 0 : parent::create($values);
    }
}
