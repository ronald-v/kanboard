<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvTaskCreationModel extends \Kanboard\Model\TaskCreationModel
{
    public function create(array $values)
    {
        return ! empty($values['time_spent']) ? 0 : parent::create($values);
    }
}
