<?php
require_once dirname(__DIR__).'/app/Core/Http/ErrorHandler.php';
\Kanboard\Core\Http\ErrorHandler::register('mcp');
require_once __DIR__.'/src/Http.php';
if (! \Projecta\Mcp\Http::bounded()) { return; }
require __DIR__.'/bootstrap.php';
use Projecta\Mcp\Http;
try {
    $container['rvSecurityModel']->consume('mcp-ip',$container['request']->getIpAddress(),1200,60);
    $request=$projectaOAuth->authenticate(Http::request());
    $directory=$projectaConfig->privateDirectory().'/mcp-sessions/'.$container['userSession']->getId();
    $builder=\Mcp\Server\Server::builder()->setServerInfo('Projecta','0.2.0','Projects, tasks and subtasks with per-user authorization.')
        ->setProtocolVersion(\Mcp\Schema\Enum\ProtocolVersion::V2025_11_25)->withoutModernEra()
        ->setSession(new \Mcp\Server\Session\FileSessionStore($directory,1800))
        ->setSessionLock(new \Mcp\Server\Session\FileSessionLock($directory,5.0));
    \Projecta\Mcp\Tools::register($builder,new \Projecta\Mcp\Bridge($container));
    $factory=new \Nyholm\Psr7\Factory\Psr17Factory;
    $host=parse_url($projectaConfig->base(),PHP_URL_HOST);
    $transport=new \Mcp\Server\Transport\StreamableHttpTransport($request,$factory,$factory,
        middleware:[new \Mcp\Server\Transport\Http\Middleware\CorsMiddleware,
            new \Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware(allowedHosts:[$host])],maxBodyBytes:262144);
    \Kanboard\Core\Http\ErrorHandler::stream();
    Http::emit($builder->build()->run($transport));
} catch (\League\OAuth2\Server\Exception\OAuthServerException $error) {
    header('WWW-Authenticate: Bearer resource_metadata="'.$projectaConfig->base().'/mcp/oauth.php?action=resource"');
    Http::json(['error'=>'unauthorized'],401);
} catch (\DomainException $error) { Http::json(['error'=>'rate_limit_exceeded'],429); }
catch (\Throwable $error) { \Kanboard\Core\Http\ErrorHandler::handle($error); }
