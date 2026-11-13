<?php

namespace KanboardTests\units\Plugin;

use KanboardTests\units\Base;
use Kanboard\Core\Plugin\Loader;
use Kanboard\Core\Security\Role;

class RvSoftwareLegacyTest extends Base
{
    public function testMigrationPreservesOpaqueHistoricalTotals()
    {
        if (getenv('RVSOFTWARE_TEST') !== 'true') { $this->markTestSkipped('Run with -Plugin'); }
        $c = $this->container;
        $project = $c['projectModel']->create(array('name' => 'Legacy'));
        $c['projectUserRoleModel']->addUser($project, 1, Role::PROJECT_MANAGER);
        $task = $c['taskCreationModel']->create(array('title' => 'Old task', 'project_id' => $project));
        $subtask = $c['subtaskModel']->create(array('title' => 'Old subtask', 'task_id' => $task, 'time_spent' => 2));
        $c['db']->table('tasks')->eq('id', $task)->update(array('time_spent' => 4));
        foreach (array('subtaskModel','subtaskTimeTrackingModel','taskCreationModel','taskModificationModel') as $key) { unset($c[$key]); }
        $loader = new Loader($c);
        $loader->initializePlugin('RvSoftware');
        $this->assertArrayHasKey('RvSoftware', $loader->getPlugins());
        $c['userSession']->initialize($c['userModel']->getById(1));
        $this->assertEquals(0, $c['db']->table('subtask_time_tracking')->count());
        $end = time() - 60;
        $c['rvTimeModel']->manual($subtask, $end - 1800, $end, '', 'legacy-preservation');
        $this->assertEquals(4.5, (float) $c['taskFinderModel']->getById($task)['time_spent']);
        $this->assertEquals(2.5, (float) $c['subtaskModel']->getById($subtask)['time_spent']);
        $this->assertEquals(1, $c['db']->table('subtask_time_tracking')->count());
        $this->assertEquals(1800, $c['db']->table('rv_time_meta')->sum('duration_seconds'));
    }
}
