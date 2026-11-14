<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
$private=__DIR__.'/../../.runtime/projecta/private';
if (! is_dir($private)) { mkdir($private,0700,true); }
if (! file_exists($private.'/oauth-private.pem')) {
    $config=$private.'/openssl.cnf';
    file_put_contents($config,"[req]\ndistinguished_name=dn\n[dn]\n");
    $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA,'config'=>$config]);
    if (! $key || ! openssl_pkey_export($key,$pem,null,['config'=>$config])) { throw new RuntimeException('Cannot create synthetic signing key'); }
    file_put_contents($private.'/oauth-private.pem',$pem);
    file_put_contents($private.'/oauth-public.pem',openssl_pkey_get_details($key)['key']);
}
require __DIR__.'/../../.runtime/projecta/app/mcp/bootstrap.php';
$httpUser=$container['userModel']->getByUsername('projecta-http');
if ($httpUser) {
    $container['rvSecurityModel']->clear('mfa-user',$httpUser['id']);
    $container['rvSecurityModel']->clear('mfa-ip','127.0.0.1');
    $container['rvSecurityModel']->clear('login-ip','127.0.0.1');
    $container['rvSecurityModel']->clear('login-account','projecta-http');
}
$projectaOAuth->authorization();
$builder=\Mcp\Server\Server::builder()->setServerInfo('Projecta','0.2.0')->withoutModernEra();
\Projecta\Mcp\Tools::register($builder,new \Projecta\Mcp\Bridge($container));
$builder->build();
$apiFixture=__DIR__.'/../../.runtime/projecta/api-fixture.json';
if (file_exists($apiFixture)) {
    $api=json_decode(file_get_contents($apiFixture),true);
    $repository=new \Projecta\Mcp\OAuth\Accesses(new \Projecta\Mcp\OAuth\Store($container,$projectaConfig));
    $client=(new \Projecta\Mcp\OAuth\Clients($projectaConfig))->getClientEntity($projectaConfig->clientId());
    $negative=[];
    foreach (['manager','viewer','reader'] as $role) {
        $scopes=$role==='reader' ? [new \Projecta\Mcp\OAuth\Scope('read')] : [new \Projecta\Mcp\OAuth\Scope('read'),new \Projecta\Mcp\OAuth\Scope('write'),new \Projecta\Mcp\OAuth\Scope('delete')];
        $userRole=$role==='reader' ? 'manager' : $role;
        $access=$repository->getNewToken($client,$scopes,(string)$api['users'][$userRole]['id']);
        $access->setIdentifier(bin2hex(random_bytes(20)));
        $access->setExpiryDateTime(new DateTimeImmutable('+10 minutes'));
        $access->setPrivateKey(new \League\OAuth2\Server\CryptKey($projectaConfig->privateKey(),null,false));
        $repository->persistNewAccessToken($access);
        $negative[$role]=$access->toString();
    }
    file_put_contents(__DIR__.'/../../.runtime/projecta/mcp-negative-fixture.json',json_encode($negative));
}
echo "PASS SDK, OAuth dependencies, 15 tool schemas and synthetic signing keys\n";
