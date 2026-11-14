<?php
// Synthetic instance only. Never import local config or accept a connection URL.
if (getenv('DB_HOSTNAME') !== '127.0.0.1' || getenv('DB_PORT') !== '13318' || getenv('DB_NAME') !== 'kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
$pdo = $container['db']->getConnection();
if (($argv[1] ?? '') === 'seed') {
    for ($i=0;$i<10;$i++) {
        $name = 'perf-user-'.$i;
        if (! $container['userModel']->getByUsername($name)) { $container['userModel']->create(['username'=>$name,'password'=>'synthetic-password','role'=>'app-manager']); }
    }
    $users = $pdo->query("SELECT id FROM users WHERE username LIKE 'perf-user-%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    for ($i=0;$i<100;$i++) {
        $name = 'Performance fixture '.$i;
        $project = $container['projectModel']->getByName($name);
        $id = $project ? $project['id'] : $container['projectModel']->create(['name'=>$name]);
        foreach ($users as $user) {
            if (! $container['projectUserRoleModel']->getUserRole($id,$user)) { $container['projectUserRoleModel']->addUser($id,$user,'project-manager'); }
        }
        $count = (int)$container['db']->table('tasks')->eq('project_id',$id)->count();
        $column = $container['db']->table('columns')->eq('project_id',$id)->asc('position')->findOneColumn('id');
        $swimlane = $container['db']->table('swimlanes')->eq('project_id',$id)->asc('position')->findOneColumn('id');
        $insert = $pdo->prepare('INSERT INTO tasks(title,project_id,column_id,swimlane_id,owner_id,creator_id,date_creation,date_modification) VALUES (?,?,?,?,?,?,?,?)');
        $pdo->beginTransaction();
        for ($j=$count;$j<100;$j++) { $insert->execute(['Synthetic task '.$j,$id,$column,$swimlane,$users[$j%10],$users[0],time(),time()]); }
        $pdo->commit();
    }
    echo "PASS synthetic fixture: 10 users, 100 projects, 10000 tasks\n";
    exit;
}
$user = $container['userModel']->getByUsername('perf-user-'.(int)($argv[1] ?? 0));
$container['userSession']->initialize($user);
$measurements = [];
for ($i=0;$i<22;$i++) {
    foreach (['leaders','overview','write_project','write_task','write_user','write_group'] as $operation) {
        $before = $container['db']->getStatementHandler()->getNbQueries();
        $start = hrtime(true);
        $created = null;
        switch ($operation) {
            case 'leaders': $container['rvPrivacyModel']->leaderProjectIds(); break;
            case 'overview': $container['rvDashboardModel']->overview($user['id']); break;
            case 'write_project': $created=$container['projectModel']->create(['name'=>'Perf write '.bin2hex(random_bytes(6))],$user['id']); break;
            case 'write_task': $project=$container['projectModel']->getByName('Performance fixture '.(int)$argv[1]); $created=$container['taskCreationModel']->create(['title'=>'Measured task','project_id'=>$project['id']]); break;
            case 'write_user': $created=$container['userModel']->create(['username'=>'perf-write-'.bin2hex(random_bytes(6))]); break;
            case 'write_group': $created=$container['groupModel']->create('Measured '.bin2hex(random_bytes(6))); break;
        }
        if ($i>=2) { $measurements[$operation][]=['ms'=>(hrtime(true)-$start)/1e6,'pico_queries'=>$container['db']->getStatementHandler()->getNbQueries()-$before]; }
        if ($created) {
            $tables = ['write_project'=>'projects','write_task'=>'tasks','write_user'=>'users','write_group'=>'groups'];
            $container['db']->table($tables[$operation])->eq('id',$created)->remove();
        }
    }
}
echo json_encode($measurements).PHP_EOL;
