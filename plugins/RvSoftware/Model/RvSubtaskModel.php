<?php

namespace Kanboard\Plugin\RvSoftware\Model;

class RvSubtaskModel extends \Kanboard\Model\SubtaskModel
{
    public function getQuery()
    {
        return $this->rvPrivacyModel->decorate(parent::getQuery(), 'subtask');
    }

    public function getById($subtaskId)
    {
        $row = parent::getById($subtaskId);
        return $row ? $this->rvPrivacyModel->subtask($row) : $row;
    }
    public function create(array $values)
    {
        return ! empty($values['time_spent']) ? false : parent::create($values);
    }

    public function update(array $values, $fireEvent = true)
    {
        if (array_key_exists('time_spent', $values)) {
            $row = $this->getById($values['id']);
            if (! $row || ! $this->rvTimeModel->sameProjection($values['time_spent'],$row['time_spent'])) { return false; }
            unset($values['time_spent']);
        }
        return parent::update($values, $fireEvent);
    }
}
