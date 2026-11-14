<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvTaskModificationModel extends \Kanboard\Model\TaskModificationModel
{
    public function update(array $values, $fire_events = true)
    {
        if (array_key_exists('time_spent', $values)) {
            $row = $this->taskFinderModel->getById($values['id']);
            if (! $row || (float) $values['time_spent'] !== (float) $row['time_spent']) { return false; }
        }
        return parent::update($values, $fire_events);
    }
}
