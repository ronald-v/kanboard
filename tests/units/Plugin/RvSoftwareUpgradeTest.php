<?php
namespace KanboardTests\units\Plugin;

use KanboardTests\units\Base;
use Kanboard\Core\Plugin\Loader;

class RvSoftwareUpgradeTest extends Base
{
    public function testUpgradeFromSchema4PreservesApprovedWorkWithoutInventingConsumption()
    {
        if (getenv('RVSOFTWARE_TEST') !== 'true') { $this->markTestSkipped('Run with -Plugin'); }
        $c = $this->container;
        $project = $c['projectModel']->create(array('name'=>'Upgrade project'));
        $task = $c['taskCreationModel']->create(array('title'=>'Old task','project_id'=>$project));
        $subtask = $c['subtaskModel']->create(array('title'=>'Old source','task_id'=>$task));
        $pdo = $c['db']->getConnection();
        require_once PLUGINS_DIR.'/RvSoftware/Schema/'.ucfirst(DB_DRIVER).'.php';
        for ($version = 1; $version <= 4; $version++) { call_user_func('Kanboard\\Plugin\\RvSoftware\\Schema\\version_'.$version,$pdo); }
        $c['db']->table('plugin_schema_versions')->insert(array('plugin'=>'rvsoftware','version'=>4));
        $c['db']->table('subtask_time_tracking')->insert(array('user_id'=>1,'subtask_id'=>$subtask,'start'=>1000,'end'=>1030,'time_spent'=>0.01));
        $id = (int)$pdo->lastInsertId();
        $c['db']->table('rv_time_meta')->insert(array('session_id'=>$id,'duration_seconds'=>30,'source'=>'manual','note'=>'Approved before bags','version'=>2,'operation_key'=>'upgrade-source','request_hash'=>'','status'=>'approved','billable'=>1,'approved_by'=>1,'approved_at'=>2000,'approved_project_id'=>$project));
        foreach (array('subtaskModel','subtaskTimeTrackingModel','taskCreationModel','taskModificationModel','taskFinderModel','taskProjectMoveModel') as $key) { unset($c[$key]); }
        $loader = new Loader($c); $loader->initializePlugin('RvSoftware');
        $this->assertArrayHasKey('RvSoftware',$loader->getPlugins());
        $c['userSession']->initialize($c['userModel']->getById(1));
        $row = $c['rvTimeModel']->session($id);
        $this->assertSame('Approved before bags',$row['note']);
        $this->assertSame('approved',$row['status']);
        $this->assertEquals(30,$row['duration_seconds']);
        $this->assertNull($row['bag_id']);
        $this->assertCount(0,$c['db']->table('rv_bag_entries')->findAll());
        $this->assertCount(1,$c['rvLedgerModel']->reconcile($project)['differences']);
        (new \Kanboard\Core\Plugin\SchemaHandler($c))->loadSchema('RvSoftware');
        $this->assertEquals(6,$c['db']->table('plugin_schema_versions')->eq('plugin','rvsoftware')->findOneColumn('version'));
        $this->assertEquals(1,$c['db']->table('rv_time_meta')->count());
    }
}
