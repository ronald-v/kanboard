<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
$path=__DIR__.'/../../.runtime/projecta/api-fixture.json';
$fixture=json_decode(file_get_contents($path),true);
$container['userSession']->initialize($container['userModel']->getById($fixture['users']['manager']['id']));
if (! $container['rvBagModel']->client($fixture['project_id'])) {
    $container['rvBagModel']->assignClient($fixture['project_id'],'Synthetic recovery customer',0,'recovery-client');
    $bag=$container['rvBagModel']->createBag($fixture['project_id'],'Synthetic recovery contract',36000,null,null,'recovery-bag');
    $container['rvTimeModel']->review($fixture['session_id'],true,true,'Synthetic recovery approval','recovery-approval',$bag);
}
if (! isset($fixture['file_id'])) {
    $fixture['file_id']=$container['taskFileModel']->uploadContent($fixture['task_id'],'projecta-recovery.txt',"Projecta synthetic attachment\n",false);
    if (! $fixture['file_id']) { throw new RuntimeException('Cannot seed recovery attachment'); }
}
file_put_contents($path,json_encode($fixture));
echo "PASS recovery fixture includes approved hour, bag ledger and attachment\n";
