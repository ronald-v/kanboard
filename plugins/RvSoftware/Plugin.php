<?php

namespace Kanboard\Plugin\RvSoftware;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;

class Plugin extends Base
{
    const VERSION = '0.2.0';
    const UPSTREAM_COMMIT = '60d2caf29edfa58e313b17cadaab76b6d4307742';

    public function initialize()
    {
        $this->helper->register('rvUserListing', '\\Kanboard\\Plugin\\RvSoftware\\Helper\\UserHelper');
        $this->container['authValidator'] = function ($c) { return new Validator\AuthValidator($c); };
        $this->container['rvSecurityModel'] = function ($c) { return new Model\RvSecurityModel($c); };
        $this->container['rvAccessModel'] = function ($c) { return new Model\RvAccessModel($c); };
        $this->container['rvPolicyModel'] = function ($c) { return new Model\RvPolicyModel($c); };
        $this->container['rvApiModel'] = function ($c) { return new Model\RvApiModel($c); };
        $this->container['rvDeletionModel'] = function ($c) { return new Model\RvDeletionModel($c); };
        $this->container['languageModel'] = function ($c) { return new Model\RvLanguageModel($c); };
        $this->container['emailClient'] = function ($c) {
            $client=new Mail\Client($c);
            return $client->setTransport('smtp','Kanboard\\Plugin\\RvSoftware\\Mail\\Smtp');
        };
        $this->applicationAccessMap->add('RvAccessController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('RvOAuthController', 'authorize', Role::APP_USER);
        $this->dispatcher->addListener('auth.success', [$this->rvAccessModel,'passwordAccepted']);
        // Projecta uses local passwords + mandatory MFA; persistent cookies cannot bypass it.
        $this->authenticationManager->reset();
        $this->authenticationManager->register(new \Kanboard\Auth\DatabaseAuth($this->container))
            ->register(new \Kanboard\Auth\TotpAuth($this->container));
        if (! in_array(DB_DRIVER, array('sqlite', 'mysql'), true)) {
            throw new \LogicException('RvSoftware requiere SQLite o MySQL; no se modificaron servicios para este motor.');
        }
        $this->applicationAccessMap->add('RvHomeController', '*', Role::APP_USER);
        $this->route->addRoute('rvsoftware', 'RvHomeController', 'show', 'RvSoftware');
        $this->hook->on('template:layout:css', array('template' => 'plugins/RvSoftware/Assets/app.css'));
        $this->hook->on('template:layout:css', ['template'=>'plugins/RvSoftware/Assets/projecta.css']);
        $this->template->setTemplateOverride('layout', 'RvSoftware:layout');
        $this->template->setTemplateOverride('auth/index', 'RvSoftware:auth/login');
        $this->template->setTemplateOverride('password_reset/email', 'RvSoftware:auth/reset_email');
        $this->template->setTemplateOverride('config/sidebar', 'RvSoftware:config/sidebar');
        $this->template->setTemplateOverride('config/email', 'RvSoftware:config/email');
        $this->template->setTemplateOverride('config/api', 'RvSoftware:config/api');
        $this->template->setTemplateOverride('header/user_dropdown', 'RvSoftware:layout/user_dropdown');
        $this->template->setTemplateOverride('user_view/sidebar', 'RvSoftware:layout/user_sidebar');
        $this->template->setTemplateOverride('user_list/listing', 'RvSoftware:user/listing');
        $this->template->setTemplateOverride('user_list/user_details', 'RvSoftware:user/details');
        $this->template->setTemplateOverride('project/sidebar', 'RvSoftware:layout/project_sidebar');
        $this->template->setTemplateOverride('task/sidebar', 'RvSoftware:layout/task_sidebar');
        $this->template->setTemplateOverride('task_creation/show', 'RvSoftware:task/create');
        $this->hook->on('template:layout:top', array('template' => 'RvSoftware:layout/sidebar'));
        $this->container['subtaskTimeTrackingModel'] = function ($c) { return new Model\RvTrackingModel($c); };
        // This stateless native service can already be resolved by legacy data
        // setup before plugin initialization. Replace its definition explicitly.
        unset($this->container['projectUserRoleModel']);
        $this->container['projectUserRoleModel'] = function ($c) { return new Model\RvProjectUserRoleModel($c); };
        unset($this->container['actionModel']);
        $this->container['actionModel'] = function ($c) { return new Model\RvActionModel($c); };
        unset($this->container['dashboardPagination']);
        $this->container['dashboardPagination'] = function ($c) { return new Model\RvDashboardPagination($c); };
        $this->container['subtaskModel'] = function ($c) { return new Model\RvSubtaskModel($c); };
        $this->container['taskCreationModel'] = function ($c) { return new Model\RvTaskCreationModel($c); };
        $this->container['taskModificationModel'] = function ($c) { return new Model\RvTaskModificationModel($c); };
        $this->container['taskFinderModel'] = function ($c) { return new Model\RvTaskFinderModel($c); };
        $this->container['taskProjectMoveModel'] = function ($c) { return new Model\RvTaskProjectMoveModel($c); };
        if (isset($this->container['api'])) {
            $this->api->getProcedureHandler()->withObject(new Api\RvTimeProcedure($this->container));
            $this->api->getProcedureHandler()->withObject(new Api\ProjectaProcedure($this->container));
            $this->api->getProcedureHandler()->withClassAndMethod('getMe',new Api\MeProcedure($this->container),'getMe');
        }
        $this->applicationAccessMap->add('RvTimeController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('RvReviewController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('RvBagController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('RvReportController', '*', Role::APP_USER);
    }

    public function getClasses()
    {
        return array('Plugin\\RvSoftware\\Model' => array('RvDashboardModel', 'RvTimeModel', 'RvPrivacyModel', 'RvBagModel', 'RvLedgerModel', 'RvReportModel'));
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
