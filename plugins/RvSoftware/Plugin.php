<?php

namespace Kanboard\Plugin\RvSoftware;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;

class Plugin extends Base
{
    const VERSION = '0.1.0';
    const UPSTREAM_COMMIT = '60d2caf29edfa58e313b17cadaab76b6d4307742';

    public function initialize()
    {
        $this->applicationAccessMap->add('RvHomeController', '*', Role::APP_USER);
        $this->route->addRoute('rvsoftware', 'RvHomeController', 'show', 'RvSoftware');
        $this->hook->on('template:layout:css', array('template' => 'plugins/RvSoftware/Assets/app.css'));
    }

    public function getPluginName() { return 'RvSoftware'; }
    public function getPluginVersion() { return self::VERSION; }
    public function getPluginDescription() { return 'Proyectos, trabajo y horas del equipo rvSoftware.'; }
    public function getPluginAuthor() { return 'rvSoftware'; }
    public function getCompatibleVersion() { return '>=1.2.57'; }
}
