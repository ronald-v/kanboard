<?php
if (getenv('DB_HOSTNAME')!=='127.0.0.1' || getenv('DB_PORT')!=='13318' || ! in_array(getenv('DB_NAME'),['kanboard_compat_http','kanboard_compat_restore'],true)) { exit(2); }
$root=realpath(getenv('PROJECTA_RECOVERY_ROOT') ?: '');
if (! $root || ! str_starts_with(strtolower(str_replace('\\','/',$root)),strtolower(str_replace('\\','/',realpath(__DIR__.'/../../.runtime'))).'/')) { exit(2); }
require $root.'/app/common.php';
require $root.'/mcp/vendor/autoload.php';
$fixture=json_decode(file_get_contents(__DIR__.'/../../.runtime/projecta/api-fixture.json'),true);
$actor=$fixture['users']['manager'];
$container['rvApiModel']->authenticate($actor['username'],$actor['token']);
$subtask=$container['rvApiModel']->invoke('getSubtask',['subtask_id'=>$fixture['subtask_id']]);
$report=$container['rvReportModel']->report('2023-11-14','2023-11-14',['project_id'=>$fixture['project_id']]);
$ledger=$container['rvLedgerModel']->reconcile($fixture['project_id']);
$file=$container['taskFileModel']->getById($fixture['file_id']);
$factor=$container['rvSecurityModel']->factor($actor['id']);
$counts=[];
foreach ($container['db']->getConnection()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if (! preg_match('/^[a-z_]+$/D',$table)) { throw new RuntimeException('Unexpected synthetic table name'); }
    $counts[$table]=(int)$container['db']->getConnection()->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
}
ksort($counts);
$config=new \Projecta\Mcp\Config;
(new \Projecta\Mcp\OAuth\Server($container,$config))->authorization();
$builder=\Mcp\Server\Server::builder()->setServerInfo('Projecta','0.2.0')->withoutModernEra();
\Projecta\Mcp\Tools::register($builder,new \Projecta\Mcp\Bridge($container));
$builder->build();
echo json_encode(['schema'=>(int)$container['db']->table('plugin_schema_versions')->eq('plugin','rvsoftware')->findOneColumn('version'),
    'counts'=>$counts,'subtask'=>$subtask['title'],'report'=>$report['totals'],'ledger'=>$ledger,
    'attachment_hash'=>hash('sha256',$container['objectStorage']->get($file['path'])),
    'factor_hash'=>hash('sha256',$container['rvSecurityModel']->unseal($factor['secret'])),'mcp_tools'=>count(\Projecta\Mcp\Tools::definitions())]).PHP_EOL;
