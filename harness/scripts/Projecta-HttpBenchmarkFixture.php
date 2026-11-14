<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
$fixture=[];
for ($i=0;$i<10;$i++) {
    $user=$container['userModel']->getByUsername('perf-user-'.$i);
    if (! $user) { throw new RuntimeException('Seed benchmark fixture first'); }
    $container['userSession']->initialize($user);
    if (! $container['rvSecurityModel']->factor($user['id'])) {
        $secret=\Otp\GoogleAuthenticator::generateRandom();
        $container['rvSecurityModel']->enroll($user['id'],$secret,(new \Otp\Otp)->totp(\Base32\Base32::decode($secret)));
    }
    $project=$container['projectModel']->getByName('Performance fixture '.$i);
    // Remove only leftover rows from the early benchmark's known fixture writes.
    foreach ($container['db']->table('tasks')->eq('project_id',$project['id'])->eq('title','Measured task')->findAll() as $leftover) { $container['taskModel']->remove($leftover['id']); }
    if ((int)$container['db']->table('tasks')->eq('project_id',$project['id'])->count()!==100) { throw new RuntimeException('Unexpected benchmark task population'); }
    $token=$container['rvApiModel']->issue($user['id'],'Synthetic HTTP benchmark');
    $admin=$container['userModel']->getByUsername('perf-admin-'.$i);
    $adminId=$admin ? $admin['id'] : $container['userModel']->create(['username'=>'perf-admin-'.$i,'role'=>'app-admin','password'=>'synthetic-admin-password']);
    if ($admin && ! $admin['password']) { $container['userModel']->update(['id'=>$adminId,'password'=>'synthetic-admin-password']); }
    if (! $container['rvSecurityModel']->factor($adminId)) {
        $secret=\Otp\GoogleAuthenticator::generateRandom();
        $container['rvSecurityModel']->enroll($adminId,$secret,(new \Otp\Otp)->totp(\Base32\Base32::decode($secret)));
    }
    $adminToken=$container['rvApiModel']->issue($adminId,'Synthetic administrative benchmark');
    $recoveries=$container['rvSecurityModel']->newRecoveryCodes($user['id']);
    $fixture[]=['username'=>$user['username'],'token'=>$token,'project_id'=>$project['id'],'admin_username'=>'perf-admin-'.$i,'admin_token'=>$adminToken,'recovery'=>$recoveries[0]];
    $container['rvSecurityModel']->clear('api-user',$user['id']);
    $container['rvSecurityModel']->clear('mfa-user',$user['id']);
    $container['rvSecurityModel']->clear('api-user',$adminId);
}
$container['rvSecurityModel']->clear('api-ip','127.0.0.1');
$container['rvSecurityModel']->clear('login-ip','127.0.0.1');
$container['rvSecurityModel']->clear('mfa-ip','127.0.0.1');
file_put_contents(__DIR__.'/../../.runtime/projecta/http-benchmark-fixture.json',json_encode($fixture));
echo "PASS HTTP benchmark identity fixture\n";
