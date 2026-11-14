<?php
namespace Kanboard\Plugin\RvSoftware\Model;

use JsonRPC\Exception\AccessDeniedException;
use JsonRPC\Exception\AuthenticationFailureException;

class RvApiModel extends RvBaseModel
{
    private $scopes=[];

    public function issue($userId, $label, array $scopes=['read','write','delete'])
    {
        $factor=$this->rvSecurityModel->factor($userId);
        $user=$this->userModel->getById($userId);
        if (! $factor || ! $user || ! $user['is_active'] || array_diff($scopes,['read','write','delete'])) { throw new \DomainException('No fue posible emitir la credencial.'); }
        $label=$this->text($label,100);
        if ($label==='') { throw new \DomainException('Indica un nombre para la credencial.'); }
        $token='prj_'.bin2hex(random_bytes(32));
        $this->sql('INSERT INTO rv_api_tokens(token_hash,user_id,label,scopes,factor_hash,password_hash,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?)',[
            hash('sha256',$token),$userId,$label,implode(' ',$scopes),hash('sha256',$factor['secret']),hash('sha256',(string)$user['password']),time()+7776000,time()]);
        $this->rvSecurityModel->audit('api.token_created',$userId);
        return $token;
    }

    public function tokens($userId)
    {
        return $this->sql('SELECT token_hash,label,scopes,expires_at,created_at FROM rv_api_tokens WHERE user_id=? ORDER BY created_at DESC',[$userId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function revoke($userId, $hash)
    {
        $this->sql('DELETE FROM rv_api_tokens WHERE user_id=? AND token_hash=?',[$userId,$hash]);
        $this->rvSecurityModel->audit('api.token_revoked',$userId);
    }

    public function authenticate($username, $token)
    {
        try {
            $this->rvSecurityModel->consume('api-ip',$this->request->getIpAddress(),6000,60);
            $row=$this->sql('SELECT * FROM rv_api_tokens WHERE token_hash=? AND expires_at>?',[hash('sha256',(string)$token),time()])->fetch(\PDO::FETCH_ASSOC);
            $user=$row ? $this->userModel->getById($row['user_id']) : false;
            $factor=$user ? $this->rvSecurityModel->factor($user['id']) : false;
            if (! $user || ! $user['is_active'] || ! hash_equals($user['username'],(string)$username) || ! $factor ||
                ! hash_equals($row['factor_hash'],hash('sha256',$factor['secret'])) || ! hash_equals($row['password_hash'],hash('sha256',(string)$user['password']))) {
                $this->rvSecurityModel->consume('api-failure',$this->request->getIpAddress(),30,300);
                throw new AuthenticationFailureException('Invalid credentials');
            }
            $this->rvSecurityModel->consume('api-user',$user['id'],600,60);
            $this->setActor($user,explode(' ',$row['scopes']));
        } catch (\DomainException $error) { throw new AuthenticationFailureException('Rate limit exceeded'); }
    }

    public function setActor(array $user, array $scopes)
    {
        if (! $user['is_active']) { throw new AuthenticationFailureException('Inactive user'); }
        session_set('scope','API');
        $this->userSession->initialize($user);
        $this->scopes=$scopes;
    }

    public function authorizeMethod($method)
    {
        if (session_get('scope')!=='API') { return; }
        if (! $this->userSession->isLogged()) { throw new AccessDeniedException('Authentication required'); }
        $disabled=['enableProjectPublicAccess','disableProjectPublicAccess','getApplicationRoles','getCurrencyRates','getAllCurrencies','createExternalTaskLink','updateExternalTaskLink','removeExternalTaskLink','getAllExternalTaskLinks'];
        if (in_array($method,$disabled,true)) { throw new AccessDeniedException('Method disabled in Projecta'); }
        $scope=preg_match('/^(get|search|projectaList|rvGet|rvTimeReport|rvReconcile)/',$method) ? 'read' : (preg_match('/^(remove|delete)/',$method) ? 'delete' : 'write');
        if (! in_array($scope,$this->scopes,true)) { throw new AccessDeniedException('Insufficient scope'); }
    }

    public function invoke($method, array $params)
    {
        $this->authorizeMethod($method);
        return $this->api->getProcedureHandler()->executeProcedure($method,$params);
    }

    public function preflight()
    {
        $length=(int)$this->request->getServerVariable('CONTENT_LENGTH');
        if (! $this->request->isPost()) { $this->response->json(['error'=>'POST required'],405); return false; }
        if ($length>262144) { $this->response->json(['error'=>'Request too large'],413); return false; }
        $input=file_get_contents('php://input',false,null,0,262145);
        if (strlen($input)>262144) { $this->response->json(['error'=>'Request too large'],413); return false; }
        $body=json_decode($input,true);
        if (is_array($body) && array_is_list($body) && count($body)>20) { $this->response->json(['error'=>'Batch limit exceeded'],413); return false; }
        return true;
    }

    public function response($encoded)
    {
        if ($encoded==='') { return ''; }
        $data=json_decode($encoded,true);
        $sanitize=function (array $row) {
            if (isset($row['error'])) {
                $code=$row['error']['code'] ?? -32603;
                $history=str_starts_with($row['error']['message'] ?? '','HISTORY_CONFLICT:');
                $known=[-32700=>'Parse error',-32600=>'Invalid Request',-32601=>'Method not found',-32602=>'Invalid params',401=>'Unauthorized',403=>'Forbidden'];
                $row['error']=$history ? ['code'=>-32009,'message'=>'History conflict; close or archive instead.'] : ['code'=>isset($known[$code]) ? $code : -32603,'message'=>$known[$code] ?? 'Internal error'];
            }
            return $row;
        };
        if (! is_array($data)) { return json_encode(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32603,'message'=>'Internal error']]); }
        return json_encode(array_is_list($data) ? array_map($sanitize,$data) : $sanitize($data));
    }
}
