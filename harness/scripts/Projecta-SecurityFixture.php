<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
$user=$container['userModel']->getByUsername('projecta-http');
$id=$user ? $user['id'] : $container['userModel']->create(['username'=>'projecta-http','password'=>'synthetic-http-password','role'=>'app-admin']);
$container['userSession']->initialize($container['userModel']->getById($id));
$container['rvSecurityModel']->resetFactor($id);
$container['rvSecurityModel']->clear('login-ip','127.0.0.1');
$container['rvSecurityModel']->clear('login-account','projecta-http');
$container['rvSecurityModel']->clear('mfa-user',$id);
$container['rvSecurityModel']->clear('mfa-ip','127.0.0.1');
echo 'PASS HTTP fixture ready; user ID '.$id.PHP_EOL;
