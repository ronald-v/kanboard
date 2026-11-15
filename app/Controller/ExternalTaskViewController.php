<?php

namespace Kanboard\Controller;


/**
 * Class ExternalTaskViewController
 *
 * @package Kanboard\Controller
 * @author  Frederic Guillot
 */
class ExternalTaskViewController extends BaseController
{
    public function show()
    {
        $task = $this->getTask();
        $taskProvider = $this->externalTaskManager->getProvider($task['external_provider']);
        $externalTask = $taskProvider->fetch($task['external_uri'], $task['project_id']);

        $this->response->html($this->template->render($taskProvider->getViewTemplate(), array(
            'task' => $task,
            'external_task' => $externalTask,
        )));
    }
}
