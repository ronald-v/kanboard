<?php
namespace KanboardTests\units\Plugin;

use Kanboard\Core\Security\Role;

class RvSoftwareDashboardPerformanceTest extends RvSoftwareBase
{
    private function summary($model, $userId)
    {
        $rows = array();
        foreach ($model->getOverview($userId) as $row) {
            $rows[] = array($row['project_id'], $row['project_name'], $row['paginator']->getTotal(), $row['paginator']->getCollection());
        }
        return $rows;
    }

    public function testBulkOverviewMatchesNativeAssignmentsTagsSubtasksAndHiddenColumns()
    {
        $c = $this->container;
        $user = $c['userModel']->create(['username'=>'dashboard-bulk-user']);
        $first = $c['projectModel']->create(['name'=>'A bulk dashboard']);
        $second = $c['projectModel']->create(['name'=>'B bulk dashboard']);
        foreach ([$first,$second] as $project) { $c['projectUserRoleModel']->addUser($project,$user,Role::PROJECT_MEMBER); }
        $c['userSession']->initialize($c['userModel']->getById($user));
        $task = $c['taskCreationModel']->create(['title'=>'Owned task','project_id'=>$first,'owner_id'=>$user,'priority'=>3]);
        $other = $c['taskCreationModel']->create(['title'=>'Assigned subtask','project_id'=>$second,'priority'=>2]);
        $c['subtaskModel']->create(['title'=>'Own subtask','task_id'=>$other,'user_id'=>$user]);
        $c['taskTagModel']->save($first,$task,['Bulk tag']);
        $native = new \Kanboard\Pagination\DashboardPagination($c);
        $this->assertEquals($this->summary($native,$user), $this->summary($c['dashboardPagination'],$user));
        $column = $c['taskFinderModel']->getById($task)['column_id'];
        $c['columnModel']->update($column,'Hidden',0,'',1);
        $this->assertEquals($this->summary($native,$user), $this->summary($c['dashboardPagination'],$user));
        $c['projectUserRoleModel']->removeUser($second,$user);
        $this->assertSame([], $c['dashboardPagination']->getOverview($user));
    }

    public function testBulkOverviewKeepsTopFifteenAndTotal()
    {
        $c = $this->container;
        $project = $c['projectModel']->create(['name'=>'Paged dashboard']);
        $c['projectUserRoleModel']->addUser($project,1,Role::PROJECT_MANAGER);
        $c['userSession']->initialize($c['userModel']->getById(1));
        for ($i=0; $i<21; $i++) { $c['taskCreationModel']->create(['title'=>'Priority '.$i,'project_id'=>$project,'owner_id'=>1,'priority'=>$i]); }
        $native = new \Kanboard\Pagination\DashboardPagination($c);
        $rows = $this->summary($c['dashboardPagination'],1);
        $this->assertEquals($this->summary($native,1), $rows);
        $this->assertEquals(21,$rows[0][2]);
        $this->assertCount(15,$rows[0][3]);
    }

    public function testExplicitPageAndQueryHooksRetainNativeBehavior()
    {
        $c = $this->container;
        $project = $c['projectModel']->create(['name'=>'Hooked dashboard']);
        $c['projectUserRoleModel']->addUser($project,1,Role::PROJECT_MANAGER);
        $c['userSession']->initialize($c['userModel']->getById(1));
        for ($i=0; $i<21; $i++) { $c['taskCreationModel']->create(['title'=>'Task '.$i,'project_id'=>$project,'owner_id'=>1,'priority'=>$i]); }
        unset($c['request']);
        $c['request'] = new \Kanboard\Core\Http\Request($c,[],['pagination'=>'tasks-'.$project,'page'=>2,'order'=>'tasks.id','direction'=>'ASC']);
        $native = new \Kanboard\Pagination\DashboardPagination($c);
        $this->assertEquals($this->summary($native,1), $this->summary($c['dashboardPagination'],1));
        unset($c['request']);
        $c['request'] = new \Kanboard\Core\Http\Request($c);
        $c['hook']->on('pagination:dashboard:task:query', function (&$query) { $query->eq('tasks.title','Task 0'); });
        $this->assertEquals($this->summary($native,1), $this->summary($c['dashboardPagination'],1));
        $this->assertEquals(1,$this->summary($c['dashboardPagination'],1)[0][2]);
    }
}
