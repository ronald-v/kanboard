<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvPolicyModel extends RvBaseModel
{
    public function disabled($controller, $action)
    {
        $controller=strtolower($controller); $action=strtolower($action);
        if (in_array($controller,['currencycontroller','plugincontroller','oauthcontroller','webhookcontroller','feedcontroller','icalendarcontroller','externaltaskcreationcontroller','externaltaskviewcontroller','taskexternallinkcontroller'])) { return true; }
        if ($action==='readonly' || $action==='rss' || $action==='ical') { return true; }
        if ($controller==='configcontroller' && ! in_array($action,['index','application','project','board','email','api','save'])) { return true; }
        if ($controller==='userviewcontroller' && in_array($action,['external','integrations','share','sessions'])) { return true; }
        if ($controller==='projectviewcontroller' && in_array($action,['share','updatesharing','integrations','updateintegrations'])) { return true; }
        return $controller==='usercredentialcontroller' && in_array($action,['changeauthentication','saveauthentication']);
    }

    public function beforeController()
    {
        $controller=strtolower($this->router->getController());
        $action=strtolower($this->router->getAction());
        if ($this->disabled($controller,$action)) {
            $this->response->text('Esta función no está disponible en Projecta.',404);
            return false;
        }
        if ($controller==='configcontroller' && $action==='index') {
            $this->response->redirect($this->helper->url->to('ConfigController','application'));
            return false;
        }
        if ($controller==='userapiaccesscontroller') {
            $this->response->redirect($this->helper->url->to('RvAccessController','manage',['plugin'=>'RvSoftware']));
            return false;
        }
        return true;
    }

    public function validateSettings(array $values)
    {
        $allowed=['csrf_token','application_url','application_language','application_timezone','application_date_format','application_time_format','password_reset','notifications_enabled',
            'application_stylesheet','board_highlight_period','board_public_refresh_interval','board_private_refresh_interval','board_columns','board_sorting_direction','board_sorting_column',
            'default_color','default_columns','project_categories','default_swimlane','default_project_owner','subtask_restriction','subtask_time_tracking','cfd_include_closed_tasks','disable_private_project'];
        if (array_diff(array_keys($values),$allowed)) { throw new \Kanboard\Core\Controller\AccessForbiddenException(); }
    }
}
