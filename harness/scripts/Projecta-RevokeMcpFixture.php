<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/mcp/bootstrap.php';
$fixture=json_decode(file_get_contents(__DIR__.'/../../.runtime/projecta/mcp-fixture.json'),true);
$parts=explode('.',$fixture['tokens']['access_token']);
$claims=json_decode(base64_decode(strtr($parts[1],'-_','+/')),true);
(new \Projecta\Mcp\OAuth\Store($container,$projectaConfig))->revoke('access',$claims['jti']);
echo "PASS synthetic MCP access token revoked\n";
