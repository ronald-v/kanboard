<?php
namespace Kanboard\Plugin\RvSoftware\Helper;

class TaskHelper extends \Kanboard\Helper\TaskHelper
{
    public function renderTimeSpentField(array $values, array $errors = array(), array $attributes = array())
    {
        return parent::renderTimeSpentField($values, $errors, array_merge($attributes, array('readonly')))
            .'<p class="form-help">Registra trabajo por sesión con fecha. '.$this->helper->url->link('Mi tiempo', 'RvTimeController', 'show', array('plugin' => 'RvSoftware')).'</p>';
    }
}
