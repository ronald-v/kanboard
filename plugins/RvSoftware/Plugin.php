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
        if (! in_array(DB_DRIVER, array('sqlite', 'mysql'), true)) {
            throw new \LogicException('RvSoftware requiere SQLite o MySQL; no se modificaron servicios para este motor.');
        }
        $this->applicationAccessMap->add('RvHomeController', '*', Role::APP_USER);
        $this->route->addRoute('rvsoftware', 'RvHomeController', 'show', 'RvSoftware');
        $this->hook->on('template:layout:css', array('template' => 'plugins/RvSoftware/Assets/app.css'));
        $this->template->setTemplateOverride('layout', 'RvSoftware:layout');
        $this->template->setTemplateOverride('task_creation/show', 'RvSoftware:task/create');
        $this->hook->on('template:layout:top', array('template' => 'RvSoftware:layout/sidebar'));
        $this->container['subtaskTimeTrackingModel'] = function ($c) { return new Model\RvTrackingModel($c); };
        $this->container['subtaskModel'] = function ($c) { return new Model\RvSubtaskModel($c); };
        $this->container['taskCreationModel'] = function ($c) { return new Model\RvTaskCreationModel($c); };
        $this->container['taskModificationModel'] = function ($c) { return new Model\RvTaskModificationModel($c); };
        $this->container['taskFinderModel'] = function ($c) { return new Model\RvTaskFinderModel($c); };
        $this->container['taskProjectMoveModel'] = function ($c) { return new Model\RvTaskProjectMoveModel($c); };
        if (isset($this->container['api'])) {
            $this->api->getProcedureHandler()->withObject(new Api\RvTimeProcedure($this->container));
        }
        $this->applicationAccessMap->add('RvTimeController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('RvReviewController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('RvBagController', '*', Role::APP_USER);
    }

    public function getClasses()
    {
        return array('Plugin\\RvSoftware\\Model' => array('RvDashboardModel', 'RvTimeModel', 'RvPrivacyModel', 'RvBagModel', 'RvLedgerModel'));
    }

    public function getHelpers()
    {
        return array('Plugin\\RvSoftware\\Helper' => array('TaskHelper', 'SubtaskHelper'));
    }

    public function getPluginName() { return 'RvSoftware'; }
    public function getPluginVersion() { return self::VERSION; }
    public function getPluginDescription() { return 'Proyectos, trabajo y horas del equipo rvSoftware.'; }
    public function getPluginAuthor() { return 'rvSoftware'; }
    public function getCompatibleVersion() { return '>=1.2.57'; }
}
