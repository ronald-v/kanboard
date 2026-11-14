<?php
namespace Kanboard\Plugin\RvSoftware\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class RvAccessController extends BaseController
{
    public function show($error = '')
    {
        $factor=$this->rvSecurityModel->factor($this->userSession->getId());
        if (! $factor && ! session_get('rv_enrollment')) {
            session_set('rv_enrollment',\Otp\GoogleAuthenticator::generateRandom(32));
        }
        $this->response->withoutCache();
        $this->response->html($this->helper->layout->app('RvSoftware:auth/mfa',[
            'title'=>'Projecta · Verificación de acceso','no_layout'=>true,
            'factor'=>$factor,'secret'=>$factor ? '' : session_get('rv_enrollment'),'error'=>$error,
        ]));
    }

    public function verify()
    {
        $this->post();
        $id=$this->userSession->getId();
        try {
            $this->rvSecurityModel->consume('mfa-user',$id,5,300);
            $this->rvSecurityModel->consume('mfa-ip',$this->request->getIpAddress(),30,300);
            $values=$this->request->getValues();
            $code=isset($values['code']) && is_string($values['code']) ? trim($values['code']) : '';
            if (! $this->rvSecurityModel->factor($id)) {
                if (time()-(int)session_get('rv_password_at')>600 || ! session_get('rv_enrollment')) { throw new \DomainException('Vuelve a ingresar con tu contraseña.'); }
                $codes=$this->rvSecurityModel->enroll($id,session_get('rv_enrollment'),$code);
                session_remove('rv_enrollment');
                $this->rvAccessModel->markValidated();
                $this->recoveryPage($codes);
                return;
            }
            if (! $this->rvSecurityModel->verify($id,$code)) { throw new \DomainException('Código inválido, vencido o ya utilizado.'); }
            $this->rvAccessModel->markValidated();
            $this->rvSecurityModel->audit('mfa.verified',$id);
            $this->response->redirect($this->rvAccessModel->returnUrl());
        } catch (\DomainException $error) { $this->show($error->getMessage()); }
    }

    public function qrcode()
    {
        if (! session_get('rv_enrollment') || $this->rvSecurityModel->factor($this->userSession->getId())) { throw new AccessForbiddenException(); }
        $url=\Otp\GoogleAuthenticator::getKeyUri('totp',$this->userSession->getUsername(),session_get('rv_enrollment'),null,['issuer'=>'Projecta']);
        $this->response->withoutCache();
        \PHPQRCode\QRcode::png($url,false,'L',6,2);
    }

    public function manage($error = '')
    {
        $this->requireValidated();
        $this->response->withoutCache();
        $this->response->html($this->helper->layout->app('RvSoftware:auth/manage',[
            'title'=>'Seguridad de Projecta','no_layout'=>false,'error'=>$error,
            'tokens'=>$this->rvApiModel->tokens($this->userSession->getId()),
        ]));
    }

    public function change()
    {
        $this->post();
        $this->requireValidated();
        try {
            $values=$this->request->getValues();
            $this->reauthenticate($values);
            $id=$this->userSession->getId();
            $operation=$values['operation'] ?? '';
            if ($operation==='recovery') {
                $codes=$this->rvSecurityModel->newRecoveryCodes($id);
                $this->rvSecurityModel->audit('mfa.recovery_regenerated',$id);
                $this->recoveryPage($codes);
            } elseif ($operation==='api-create') {
                $label=is_string($values['label'] ?? null) ? $values['label'] : '';
                $token=$this->rvApiModel->issue($id,$label);
                $this->response->withoutCache()->withHeader('Cache-Control','no-store');
                $this->response->html($this->helper->layout->app('RvSoftware:auth/token',['title'=>'Credencial API · Projecta','no_layout'=>true,'apiToken'=>$token]));
            } elseif ($operation==='api-revoke') {
                $this->rvApiModel->revoke($id,(string)($values['token_id'] ?? ''));
                $this->manage('Credencial revocada.');
            } elseif ($operation==='oauth-revoke') {
                $this->rvSecurityModel->sql('UPDATE rv_oauth_grants SET revoked=1 WHERE user_id=?',[$id]);
                $this->rvSecurityModel->audit('oauth.revoked',$id);
                $this->manage('Conexión de ChatGPT revocada.');
            } elseif ($operation==='replace') {
                $this->rvSecurityModel->resetFactor($id);
                session_remove('rv_mfa_at');
                $this->show();
            } elseif ($operation==='reset-user' && $this->userSession->isAdmin()) {
                $target=filter_var($values['target_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                if (! $target || ! $this->userModel->getById($target)) { throw new \DomainException('Usuario inválido.'); }
                $this->rvSecurityModel->resetFactor($target);
                $this->manage('Factor reiniciado. El usuario deberá configurar TOTP en su siguiente acceso.');
            } else { throw new \DomainException('Operación inválida.'); }
        } catch (\DomainException $error) { $this->manage($error->getMessage()); }
    }

    private function reauthenticate(array $values)
    {
        $id=$this->userSession->getId();
        $this->rvSecurityModel->consume('reauth',$id,5,300);
        $user=$this->userModel->getById($id);
        $password=is_string($values['password'] ?? null) ? $values['password'] : '';
        $code=is_string($values['code'] ?? null) ? trim($values['code']) : '';
        if (! password_verify($password,(string)$user['password']) || ! $this->rvSecurityModel->verify($id,$code)) { throw new \DomainException('No fue posible verificar tu identidad.'); }
        session_set('rv_password_at',time());
        $this->rvAccessModel->markValidated();
    }

    private function requireValidated()
    {
        if (! session_get('rv_mfa_at')) { throw new AccessForbiddenException(); }
        $factor=$this->rvSecurityModel->factor($this->userSession->getId());
        if (! $factor || ! hash_equals(hash('sha256',$factor['secret']),(string)session_get('rv_mfa_generation'))) { throw new AccessForbiddenException(); }
    }

    private function post()
    {
        if (! $this->request->isPost()) { throw new AccessForbiddenException(); }
        $this->checkCSRFForm();
    }

    private function recoveryPage(array $codes)
    {
        $this->response->withoutCache();
        $this->response->html($this->helper->layout->app('RvSoftware:auth/recovery',[
            'title'=>'Projecta · Códigos de recuperación','no_layout'=>true,'codes'=>$codes,'returnUrl'=>$this->rvAccessModel->returnUrl(),
        ]));
    }
}
