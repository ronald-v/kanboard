<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvAccessModel extends RvBaseModel
{
    public function passwordAccepted()
    {
        if (session_get('scope') === 'API') { return; }
        session_set('rv_password_at',time());
        session_set('rv_started_at',time());
        session_remove('rv_mfa_generation');
        session_remove('rv_mfa_at');
    }

    public function guard()
    {
        if (! $this->userSession->isLogged()) { return true; }
        $now=time();
        $expired = ! session_get('rv_started_at') || $now-(int)session_get('rv_started_at')>28800 ||
            (session_get('rv_last_at') && $now-(int)session_get('rv_last_at')>1800);
        if ($expired) {
            $this->sessionManager->close();
            $this->response->redirect($this->helper->url->to('AuthController','login'));
            return false;
        }
        $poll=strtolower($this->router->getController())==='userajaxcontroller' && strtolower($this->router->getAction())==='status';
        if (! $poll) { session_set('rv_last_at',$now); }
        $factor=$this->rvSecurityModel->factor($this->userSession->getId());
        $valid=$factor && session_get('rv_mfa_at') && hash_equals(hash('sha256',$factor['secret']), (string)session_get('rv_mfa_generation'));
        $controller=strtolower($this->router->getController());
        $action=strtolower($this->router->getAction());
        $own=$controller==='rvaccesscontroller' && strtolower($this->router->getPlugin())==='rvsoftware';
        if ($controller==='authcontroller' && $action==='logout') { return true; }
        if ($own) { return true; }
        if (! $valid || $controller==='twofactorcontroller') {
            if ($controller==='rvoauthcontroller' && $this->request->isSafeRedirectUri($this->request->getUri())) { session_set('rv_after_mfa',$this->request->getUri()); }
            $this->response->redirect($this->helper->url->to('RvAccessController','show',['plugin'=>'RvSoftware']));
            return false;
        }
        $this->userSession->setPostAuthenticationAsValidated();
        return true;
    }

    public function markValidated()
    {
        $factor=$this->rvSecurityModel->factor($this->userSession->getId());
        if (session_status()===PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        session_set('rv_mfa_at',time());
        session_set('rv_last_at',time());
        session_set('rv_mfa_generation',hash('sha256',$factor['secret']));
        $this->userSession->setPostAuthenticationAsValidated();
    }

    public function recent()
    {
        return time()-(int)session_get('rv_mfa_at')<=300 && time()-(int)session_get('rv_password_at')<=300;
    }

    public function returnUrl()
    {
        $url=(string)session_get('rv_after_mfa');
        session_remove('rv_after_mfa');
        return $url && $this->request->isSafeRedirectUri($url) ? $url : $this->helper->url->to('RvHomeController','show',['plugin'=>'RvSoftware']);
    }
}
