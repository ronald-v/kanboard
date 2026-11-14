<?php
// Root dependency versions remain intact; adapter dependencies are isolated and locked.
require_once dirname(__DIR__).'/app/common.php';
require_once __DIR__.'/vendor/autoload.php';
$projectaConfig=new \Projecta\Mcp\Config;
$projectaOAuth=new \Projecta\Mcp\OAuth\Server($container,$projectaConfig);
