<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
$user=$container['userModel']->getByUsername('projecta-http');
$container['userSession']->initialize($user);
$project=$container['projectModel']->getByName('Projecta UI fixture');
$projectId=$project ? $project['id'] : $container['projectModel']->create(['name'=>'Projecta UI fixture'],$user['id']);
$task=$container['taskCreationModel']->create(['title'=>'Synthetic UI task','project_id'=>$projectId]);
$subtask=$container['subtaskModel']->create(['title'=>'Synthetic UI subtask','task_id'=>$task]);
$group=$container['groupModel']->create('Projecta UI group '.bin2hex(random_bytes(3)));
if (! $container['rvBagModel']->client($projectId)) {
    $container['rvBagModel']->assignClient($projectId,'Synthetic UI client',0,'ui-client-create');
    $container['rvBagModel']->createBag($projectId,'Synthetic contract',36000,null,null,'ui-bag-create');
}
file_put_contents(__DIR__.'/../../.runtime/projecta/ui-fixture.json',json_encode(['project_id'=>$projectId,'task_id'=>$task,'subtask_id'=>$subtask,'group_id'=>$group,'user_id'=>$user['id']]));
echo "PASS UI fixture ready\n";
