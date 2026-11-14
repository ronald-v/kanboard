<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
$fixture=['users'=>[]];
foreach (['admin'=>'app-admin','manager'=>'app-manager','member'=>'app-user','viewer'=>'app-user','foreign'=>'app-manager'] as $name=>$role) {
    $username='api-test-'.$name;
    $user=$container['userModel']->getByUsername($username);
    $id=$user ? $user['id'] : $container['userModel']->create(['username'=>$username,'password'=>'synthetic-api-password','role'=>$role]);
    $container['userSession']->initialize($container['userModel']->getById($id));
    if (! $container['rvSecurityModel']->factor($id)) {
        $secret=\Otp\GoogleAuthenticator::generateRandom();
        $container['rvSecurityModel']->enroll($id,$secret,(new \Otp\Otp)->totp(\Base32\Base32::decode($secret)));
    }
    $token=$container['rvApiModel']->issue($id,'HTTP contract test');
    $fixture['users'][$name]=['id'=>$id,'username'=>$username,'token'=>$token];
}
$container['userSession']->initialize($container['userModel']->getById($fixture['users']['admin']['id']));
$project=$container['projectModel']->create(['name'=>'API contract '.bin2hex(random_bytes(5))],$fixture['users']['manager']['id'],true);
$other=$container['projectModel']->create(['name'=>'API foreign '.bin2hex(random_bytes(5))],$fixture['users']['foreign']['id'],true);
$container['projectUserRoleModel']->addUser($project,$fixture['users']['member']['id'],'project-member');
$container['projectUserRoleModel']->addUser($project,$fixture['users']['viewer']['id'],'project-viewer');
$httpUser=$container['userModel']->getByUsername('projecta-http');
if ($httpUser) { $container['projectUserRoleModel']->addUser($project,$httpUser['id'],'project-manager'); }
$task=$container['taskCreationModel']->create(['title'=>'API protected task','project_id'=>$project]);
$subtask=$container['subtaskModel']->create(['title'=>'API protected subtask','task_id'=>$task]);
$session=$container['rvTimeModel']->manual($subtask,1700000000,1700003600,'Synthetic protected hour','api-protected-'.bin2hex(random_bytes(5)));
$foreignTask=$container['taskCreationModel']->create(['title'=>'Foreign task','project_id'=>$other]);
$fixture+=['project_id'=>$project,'other_project_id'=>$other,'task_id'=>$task,'subtask_id'=>$subtask,'foreign_task_id'=>$foreignTask,'session_id'=>$session];
$fixture['read_token']=$container['rvApiModel']->issue($fixture['users']['manager']['id'],'Read only',['read']);
$fixture['global_token']=$container['configModel']->get('api_token');
$container['rvSecurityModel']->clear('api-failure','127.0.0.1');
$container['rvSecurityModel']->clear('api-ip','127.0.0.1');
file_put_contents(__DIR__.'/../../.runtime/projecta/api-fixture.json',json_encode($fixture));
echo "PASS individual API tokens and protected history fixture\n";
