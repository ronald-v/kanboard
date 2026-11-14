<?php
namespace Projecta\Mcp\OAuth;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Projecta\Mcp\Config;

final class Server
{
    private readonly Store $store;
    public function __construct(private readonly \Pimple\Container $container, private readonly Config $config)
    {
        $this->store=new Store($container,$config);
    }
    public function authorization(): AuthorizationServer
    {
        $server=new AuthorizationServer(new Clients($this->config),new Accesses($this->store),new Scopes,
            new CryptKey($this->config->privateKey(),null,false),bin2hex($this->container['rvSecurityModel']->key()));
        $codes=new AuthCodeGrant(new Codes($this->store),new Refreshes($this->store),new \DateInterval('PT5M'));
        $codes->setRefreshTokenTTL(new \DateInterval('P30D'));
        $refresh=new RefreshTokenGrant(new Refreshes($this->store));
        $refresh->setRefreshTokenTTL(new \DateInterval('P30D'));
        $server->enableGrantType($codes,new \DateInterval('PT10M'));
        $server->enableGrantType($refresh,new \DateInterval('PT10M'));
        $server->setDefaultScope('read');
        return $server;
    }
    public function authorizationRequest(array $params): \League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface
    {
        if (($params['resource'] ?? '')!==$this->config->endpoint() || ($params['code_challenge_method'] ?? '')!=='S256' || empty($params['state'])) {
            throw \League\OAuth2\Server\Exception\OAuthServerException::invalidRequest('resource, state or PKCE S256');
        }
        $request=(new ServerRequest('GET',$this->config->base()))->withQueryParams($params);
        return $this->authorization()->validateAuthorizationRequest($request);
    }
    public function exchange(array $params): \Psr\Http\Message\ResponseInterface
    {
        if (($params['resource'] ?? '')!==$this->config->endpoint()) { throw \League\OAuth2\Server\Exception\OAuthServerException::invalidRequest('resource'); }
        $pdo=$this->container['db']->getConnection();
        $pdo->beginTransaction();
        try {
            $request=(new ServerRequest('POST',$this->config->base().'/mcp/oauth.php?action=token'))->withParsedBody($params);
            $response=$this->authorization()->respondToAccessTokenRequest($request,new Response);
            $pdo->commit();
            return $response;
        } catch (\Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    }
    public function authenticate(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ServerRequestInterface
    {
        $validated=(new ResourceServer(new Accesses($this->store),new CryptKey($this->config->publicKey(),null,false)))->validateAuthenticatedRequest($request);
        $raw=preg_replace('/^Bearer\s+/i','',$request->getHeaderLine('Authorization'));
        $parts=explode('.',$raw);
        $claims=count($parts)===3 ? json_decode(base64_decode(strtr($parts[1],'-_','+/')),true) : null;
        if (! is_array($claims) || ($claims['iss'] ?? '')!==$this->config->base() || ! in_array($this->config->endpoint(),(array)($claims['aud'] ?? []),true)) {
            throw \League\OAuth2\Server\Exception\OAuthServerException::accessDenied('Invalid resource audience');
        }
        $user=$this->container['userModel']->getById((int)$validated->getAttribute('oauth_user_id'));
        if (! $user) { throw \League\OAuth2\Server\Exception\OAuthServerException::accessDenied(); }
        $this->container['rvSecurityModel']->consume('mcp-user',$user['id'],600,60);
        $this->container['rvApiModel']->setActor($user,$validated->getAttribute('oauth_scopes',[]));
        return $validated->withAttribute(\Mcp\Server\Transport\StreamableHttpTransport::PRINCIPAL_ATTRIBUTE,(string)$user['id']);
    }
    public function metadata(): array
    {
        $base=$this->config->base();
        return ['issuer'=>$base,'authorization_endpoint'=>$base.'/?controller=RvOAuthController&action=authorize&plugin=RvSoftware',
            'token_endpoint'=>$base.'/mcp/oauth.php?action=token','response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],
            'token_endpoint_auth_methods_supported'=>['none'],'code_challenge_methods_supported'=>['S256'],'scopes_supported'=>['read','write','delete']];
    }
    public function resource(): array
    {
        return ['resource'=>$this->config->endpoint(),'authorization_servers'=>[$this->config->base()],'scopes_supported'=>['read','write','delete'],'resource_name'=>'Projecta'];
    }
}
