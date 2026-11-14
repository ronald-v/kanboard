<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
$path=__DIR__.'/../../.runtime/projecta/race-fixture.json';
if (($argv[1] ?? '')==='seed') {
    $user=$container['userModel']->getByUsername('security-race');
    $id=$user ? $user['id'] : $container['userModel']->create(['username'=>'security-race']);
    $m=$container['rvSecurityModel'];
    $m->resetFactor($id);
    $m->clear('race','shared');
    $secret=\Otp\GoogleAuthenticator::generateRandom();
    $codes=$m->enroll($id,$secret,(new \Otp\Otp)->totp(\Base32\Base32::decode($secret)));
    file_put_contents($path,json_encode(['id'=>$id,'code'=>$codes[0]]));
    echo "PASS race fixture\n";
    exit;
}
$fixture=json_decode(file_get_contents($path),true);
$used=$container['rvSecurityModel']->verify($fixture['id'],$fixture['code']);
try { $container['rvSecurityModel']->consume('race','shared',5,300); $limited=false; } catch (\DomainException $error) { $limited=true; }
echo json_encode(['recovery_used'=>$used,'limited'=>$limited]).PHP_EOL;
