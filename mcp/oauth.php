<?php
require_once __DIR__.'/src/Http.php';
if (! \Projecta\Mcp\Http::bounded()) { return; }
require __DIR__.'/bootstrap.php';
use Projecta\Mcp\Http;
try {
    $action=$_GET['action'] ?? '';
    if ($action==='metadata') { Http::json($projectaOAuth->metadata()); }
    elseif ($action==='resource') { Http::json($projectaOAuth->resource()); }
    elseif ($action==='token' && ($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
        $container['rvSecurityModel']->consume('oauth-token-ip',$container['request']->getIpAddress(),60,60);
        Http::emit($projectaOAuth->exchange($_POST));
    } else { Http::json(['error'=>'not_found'],404); }
} catch (\League\OAuth2\Server\Exception\OAuthServerException $error) { Http::emit($error->generateHttpResponse(new \Nyholm\Psr7\Response)); }
catch (\Throwable $error) { Http::json(['error'=>'oauth_unavailable'],503); }
