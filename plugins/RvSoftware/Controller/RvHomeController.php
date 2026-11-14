<?php

namespace Kanboard\Plugin\RvSoftware\Controller;

use Kanboard\Controller\BaseController;

class RvHomeController extends BaseController
{
    public function show()
    {
        $this->response->html($this->helper->layout->app('RvSoftware:home/index', array(
            'title' => 'rvSoftware',
            'overview' => $this->rvDashboardModel->overview($this->userSession->getId()),
        )));
    }

    public function projects()
    {
        $this->response->html($this->helper->layout->app('RvSoftware:home/projects', array(
            'title' => 'Mis proyectos',
            'projects' => $this->rvDashboardModel->projects($this->userSession->getId()),
        )));
    }
}
