<?php

namespace Kanboard\Plugin\RvSoftware\Model;

class RvSubtaskModel extends \Kanboard\Model\SubtaskModel
{
    public function create(array $values)
    {
        return ! empty($values['time_spent']) ? false : parent::create($values);
    }

    public function update(array $values, $fireEvent = true)
    {
        if (array_key_exists('time_spent', $values)) {
            $row = $this->getById($values['id']);
            if (! $row || (float) $values['time_spent'] !== (float) $row['time_spent']) { return false; }
        }
        return parent::update($values, $fireEvent);
    }
}
