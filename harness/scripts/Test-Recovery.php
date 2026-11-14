<?php
// Run only in a child process configured to a synthetic restored SQLite copy.
if (getenv('RV_RECOVERY_TEST') !== 'true' || getenv('DB_DRIVER') !== 'sqlite') { exit(2); }
require getenv('RV_RECOVERY_ROOT').'/app/common.php';
$container['userSession']->initialize($container['userModel']->getById(1));
$id = (int)getenv('RV_RECOVERY_PROJECT');
$report = $container['rvReportModel']->report('2026-10-01','2026-10-01',array('project_id'=>$id));
$ledger = $container['rvLedgerModel']->reconcile($id);
$file = $container['taskFileModel']->getById((int)getenv('RV_RECOVERY_FILE'));
$hash = hash('sha256',$container['objectStorage']->get($file['path']));
echo json_encode(array('totals'=>$report['totals'],'bags'=>$ledger['bags'],'differences'=>$ledger['differences'],'file_hash'=>$hash,'schema'=>$container['db']->table('plugin_schema_versions')->eq('plugin','rvsoftware')->findOneColumn('version')));
