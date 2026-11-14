<?php
// Fixed, disposable database only. No connection settings from arguments or files.
if (PHP_SAPI !== 'cli' || getenv('DB_HOSTNAME') !== '127.0.0.1' || getenv('DB_PORT') !== '13318' || getenv('DB_NAME') !== 'kanboard_compat_http') { exit(2); }
$pdo = new PDO('mysql:host=127.0.0.1;port=13318', 'compat_synthetic', 'synthetic-local-only', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server = $pdo->query('SELECT @@datadir AS datadir, @@port AS port')->fetch(PDO::FETCH_ASSOC);
if (realpath($server['datadir']) !== realpath(dirname(__DIR__,2).'/.runtime/compatibility/maria-data') || (int)$server['port'] !== 13318) { exit(2); }
if (getenv('F019_GUARD_ONLY') === 'true') { echo "PASS fixed synthetic server identity\n"; exit; }
if (getenv('F019_RESET') === 'true') {
    $pdo->exec('DROP DATABASE IF EXISTS kanboard_compat_http');
    $pdo->exec('CREATE DATABASE kanboard_compat_http CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
require getenv('F019_APP').'/app/common.php';
$fixture = [];
for ($i=0; $i<10; $i++) {
    $name = 'f019-admin-'.$i;
    $user = $container['userModel']->getByUsername($name);
    $id = $user ? $user['id'] : $container['userModel']->create(['username'=>$name, 'password'=>'synthetic-password', 'role'=>'app-admin']);
    $container['userSession']->initialize($container['userModel']->getById($id));
    $secret = \Otp\GoogleAuthenticator::generateRandom();
    if (! $container['rvSecurityModel']->factor($id)) {
        $container['rvSecurityModel']->enroll($id, $secret, (new \Otp\Otp)->totp(\Base32\Base32::decode($secret)));
    }
    $codes = $container['rvSecurityModel']->newRecoveryCodes($id);
    $project = $container['projectModel']->getByName('F019 fixture '.$i);
    $projectId = $project ? $project['id'] : $container['projectModel']->create(['name'=>'F019 fixture '.$i], $id);
    $task = $container['db']->table('tasks')->eq('project_id', $projectId)->eq('title', 'F019 fixture task')->findOne();
    $taskId = $task ? $task['id'] : $container['taskCreationModel']->create(['title'=>'F019 fixture task','project_id'=>$projectId]);
    $subtask = $container['db']->table('subtasks')->eq('task_id', $taskId)->findOne();
    $subtaskId = $subtask ? $subtask['id'] : $container['subtaskModel']->create(['title'=>'F019 fixture subtask','task_id'=>$taskId]);
    $project = $container['projectModel']->getById($projectId);
    $column = $container['db']->table('columns')->eq('project_id',$projectId)->asc('position')->findOneColumn('id');
    $swimlane = $container['db']->table('swimlanes')->eq('project_id',$projectId)->asc('position')->findOneColumn('id');
    $fixture[] = ['username'=>$name, 'password'=>'synthetic-password', 'recovery'=>$codes[0], 'user_id'=>$id, 'project_id'=>$projectId, 'task_id'=>$taskId, 'subtask_id'=>$subtaskId, 'column_id'=>$column, 'swimlane_id'=>$swimlane];
    foreach (['login-user','mfa-user'] as $kind) { $container['rvSecurityModel']->clear($kind, $id); }
}
$pdo = $container['db']->getConnection();
for ($i=0; $i<100; $i++) {
    $project = $container['projectModel']->getByName('F019 fixture '.$i);
    $projectId = $project ? $project['id'] : $container['projectModel']->create(['name'=>'F019 fixture '.$i], $fixture[0]['user_id']);
    foreach ($fixture as $user) {
        if (! $container['projectUserRoleModel']->getUserRole($projectId,$user['user_id'])) { $container['projectUserRoleModel']->addUser($projectId,$user['user_id'],'project-manager'); }
    }
    $count = $container['db']->table('tasks')->eq('project_id',$projectId)->count();
    $column = $container['db']->table('columns')->eq('project_id',$projectId)->asc('position')->findOneColumn('id');
    $swimlane = $container['db']->table('swimlanes')->eq('project_id',$projectId)->asc('position')->findOneColumn('id');
    $insert = $pdo->prepare('INSERT INTO tasks(title,project_id,column_id,swimlane_id,owner_id,creator_id,date_creation,date_modification) VALUES (?,?,?,?,?,?,?,?)');
    $pdo->beginTransaction();
    for ($j=$count; $j<100; $j++) { $insert->execute(['Synthetic task '.$j,$projectId,$column,$swimlane,$fixture[$j%10]['user_id'],$fixture[0]['user_id'],time(),time()]); }
    $pdo->commit();
}
foreach (['login-ip','mfa-ip'] as $kind) { $container['rvSecurityModel']->clear($kind, '127.0.0.1'); }
file_put_contents(dirname(__DIR__,2).'/.runtime/f019/fixture.json', json_encode($fixture));
echo "PASS ten isolated browser identities and CRUD fixtures\n";
