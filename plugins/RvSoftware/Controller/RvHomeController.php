<?php

namespace Kanboard\Plugin\RvSoftware\Controller;

use Kanboard\Controller\BaseController;

class RvHomeController extends BaseController
{
    public function show()
    {
        $this->response->html($this->helper->layout->app('RvSoftware:home/index', array(
            'title' => 'rvSoftware',
        )));
    }
}
