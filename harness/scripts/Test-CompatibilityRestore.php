<?php
if (getenv('DB_HOSTNAME') !== '127.0.0.1' || getenv('DB_PORT') !== '13318' || getenv('DB_NAME') !== 'kanboard_compat_restore') { exit(2); }
$fixture = json_decode(file_get_contents(getenv('RV_COMPAT_FIXTURE')),true,512,JSON_THROW_ON_ERROR);
require getenv('RV_COMPAT_APP').'/app/common.php';
$container['userSession']->initialize($container['userModel']->getById(1));
$report = $container['rvReportModel']->report('2026-10-01','2026-10-01',array('project_id'=>$fixture['project']));
$ledger = $container['rvLedgerModel']->reconcile($fixture['project']);
$file = $container['taskFileModel']->getById($fixture['file_id']);
$pdo = $container['db']->getConnection();
$engines = $pdo->query("SELECT DISTINCT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'rv_%'")->fetchAll(PDO::FETCH_COLUMN);
$schema = $pdo->query("SELECT version FROM plugin_schema_versions WHERE plugin='rvsoftware'")->fetchColumn();
echo json_encode(array('totals'=>$report['totals'],'balance'=>(int)$ledger['bags'][0]['balance_seconds'],'differences'=>$ledger['differences'],'file_hash'=>hash('sha256',$container['objectStorage']->get($file['path'])),'engines'=>$engines,'schema'=>(int)$schema,'php'=>PHP_VERSION,'maria'=>$pdo->query('SELECT VERSION()')->fetchColumn()),JSON_THROW_ON_ERROR);
