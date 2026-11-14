<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvTaskModificationModel extends \Kanboard\Model\TaskModificationModel
{
    public function update(array $values, $fire_events = true)
    {
        if (array_key_exists('time_spent', $values)) {
            $row = $this->taskFinderModel->getById($values['id']);
            if (! $row || ! $this->rvTimeModel->sameProjection($values['time_spent'],$row['time_spent'])) { return false; }
            unset($values['time_spent']);
        }
        return parent::update($values, $fire_events);
    }
}
