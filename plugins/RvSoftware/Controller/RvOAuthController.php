<?php
namespace Kanboard\Plugin\RvSoftware\Controller;

class RvOAuthController extends \Kanboard\Controller\BaseController
{
    public function authorize()
    {
        require_once ROOT_DIR.'/mcp/vendor/autoload.php';
        $server=new \Projecta\Mcp\OAuth\Server($this->container,new \Projecta\Mcp\Config);
        $params=$this->request->isPost() ? $this->request->getRawFormValues() : $_GET;
        try {
            $authorization=$server->authorizationRequest($params);
            if ($this->request->isPost()) {
                $this->checkCSRFForm();
                if (time()-(int)session_get('rv_mfa_at')>300) { throw new \Kanboard\Core\Controller\AccessForbiddenException(); }
                $authorization->setUser(new \Projecta\Mcp\OAuth\User($this->userSession->getId()));
                $authorization->setAuthorizationApproved(($params['decision'] ?? '')==='allow');
                $response=$server->authorization()->completeAuthorizationRequest($authorization,new \Nyholm\Psr7\Response);
                $this->rvSecurityModel->audit('oauth.consent',$this->userSession->getId());
                $this->response->redirect($response->getHeaderLine('Location'));
                return;
            }
            if (time()-(int)session_get('rv_mfa_at')>300) {
                session_set('rv_after_mfa',$this->request->getUri());
                $this->response->redirect($this->helper->url->to('RvAccessController','show',['plugin'=>'RvSoftware']));
                return;
            }
            $this->response->html($this->helper->layout->app('RvSoftware:auth/consent',[
                'title'=>'Conectar ChatGPT con Projecta','no_layout'=>true,'params'=>$params,'scopes'=>$authorization->getScopes(),
            ]));
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $error) {
            $this->response->text('La solicitud de conexión no es válida.',400);
        } catch (\Kanboard\Core\Controller\AccessForbiddenException $error) { throw $error; }
    }
}
