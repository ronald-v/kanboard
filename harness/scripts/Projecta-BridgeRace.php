<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || getenv('DB_NAME')!=='kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/mcp/bootstrap.php';
$path=__DIR__.'/../../.runtime/projecta/bridge-race.json';
if (($argv[1] ?? '')==='seed') {
    $fixture=json_decode(file_get_contents(__DIR__.'/../../.runtime/projecta/api-fixture.json'),true);
    file_put_contents($path,json_encode(['user_id'=>$fixture['users']['manager']['id'],'key'=>bin2hex(random_bytes(16)),'name'=>'MCP race '.bin2hex(random_bytes(12))]));
    echo "PASS race seeded\n"; exit;
}
$fixture=json_decode(file_get_contents($path),true);
if (($argv[1] ?? '')==='check') {
    $count=$container['db']->table('projects')->eq('name',$fixture['name'])->count();
    echo json_encode(['count'=>$count]).PHP_EOL; exit;
}
$container['rvApiModel']->setActor($container['userModel']->getById($fixture['user_id']),['read','write','delete']);
$result=(new \Projecta\Mcp\Bridge($container))->call('projects_create',['fields'=>['name'=>$fixture['name']],'operation_key'=>$fixture['key']]);
echo json_encode($result->structuredContent).PHP_EOL;
