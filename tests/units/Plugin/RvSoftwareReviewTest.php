<?php
namespace KanboardTests\units\Plugin;

use DomainException;
use Kanboard\Core\Security\Role;

class RvSoftwareReviewTest extends RvSoftwareBase
{
    private $project;
    private $task;
    private $subtask;
    private $member;
    private $peer;
    private $leader;
    private $first;
    private $second;
    private $end;
    private $bag;

    protected function setUp(): void
    {
        parent::setUp();
        $c = $this->container;
        $this->project = $c['projectModel']->create(array('name' => 'Review'));
        foreach (array('member','peer','leader') as $name) {
            $this->$name = $c['userModel']->create(array('username' => $name));
            $c['projectUserRoleModel']->addUser($this->project, $this->$name, $name === 'leader' ? Role::PROJECT_MANAGER : Role::PROJECT_MEMBER);
        }
        $this->task = $c['taskCreationModel']->create(array('title' => 'Review task','reference' => 'REF', 'project_id' => $this->project));
        $this->subtask = $c['subtaskModel']->create(array('title' => 'Review source','task_id' => $this->task));
        $this->end = time() - 60;
        $this->asUser($this->leader);
        $c['rvBagModel']->assignClient($this->project,'Client',0,'review-client');
        $this->bag = $c['rvBagModel']->createBag($this->project,'Bag',36000,null,null,'review-bag');
        $this->asUser($this->member);
        $this->first = $c['rvTimeModel']->manual($this->subtask, $this->end - 3600, $this->end, 'Member', 'member-time');
        $this->asUser($this->peer);
        $this->second = $c['rvTimeModel']->manual($this->subtask, $this->end - 1800, $this->end, 'Peer', 'peer-time');
    }

    private function asUser($id)
    {
        $this->container['userSession']->initialize($this->container['userModel']->getById($id));
    }

    public function testClassificationApprovalAndRectificationAreAudited()
    {
        $c = $this->container;
        $m = $c['rvTimeModel'];
        $this->asUser($this->leader);
        $m->review($this->first, true, false, 'Classify', 'classify-time');
        $this->assertSame('pending', $m->session($this->first)['status']);
        $m->review($this->first, true, true, 'Approve', 'approve-time',$this->bag);
        $m->review($this->first, true, true, 'Approve', 'approve-time',$this->bag);
        $m->review($this->second, false, true, 'Internal', 'approve-peer');
        $this->assertEquals(5400, array_sum(array_column($m->visible(), 'duration_seconds')));
        $this->assertEquals(3600, $c['db']->table('rv_time_meta')->eq('billable', 1)->sum('duration_seconds'));
        $m->rectify($this->first, $this->end - 2400, $this->end, false, 'Correction', 'Wrong duration and class', 'rectify-time');
        $m->rectify($this->first, $this->end - 2400, $this->end, false, 'Correction', 'Wrong duration and class', 'rectify-time');
        $this->assertEquals(2400, $m->session($this->first)['duration_seconds']);
        $this->assertEquals(0, $m->session($this->first)['billable']);
        $this->assertCount(4, $m->history($this->first));
        $this->assertSame('approved', $m->session($this->first)['status']);
        $this->assertFalse($c['taskProjectMoveModel']->moveToProject($this->task, $c['projectModel']->create(array('name' => 'Other'))));
        $this->asUser($this->member);
        $this->expectException(DomainException::class);
        $m->correct($this->first, $this->end - 60, $this->end, '', 'Denied', 'denied-approved');
    }

    public function testMemberCannotClassifyEvenOwnWork()
    {
        $this->asUser($this->member);
        $this->expectException(DomainException::class);
        $this->container['rvTimeModel']->review($this->first, true, true, '', 'denied-review');
    }

    public function testNativeReadsMaskOtherUsersAndWritesPreserveActualAggregate()
    {
        $c = $this->container;
        $this->asUser($this->member);
        $this->assertCount(1, $c['rvTimeModel']->visible());
        foreach (array($c['taskFinderModel']->getById($this->task), $c['taskFinderModel']->getDetails($this->task), $c['taskFinderModel']->getByReference($this->project, 'REF'), $c['taskFinderModel']->getAll($this->project)[0], $c['taskFinderModel']->getExtendedQuery()->findOne()) as $row) {
            $this->assertEquals(1, $row['time_spent']);
        }
        $this->assertEquals(1, $c['subtaskModel']->getAll($this->task)[0]['time_spent']);
        $this->assertCount(1, $c['subtaskTimeTrackingModel']->getTaskQuery($this->task)->findAll());
        $this->assertCount(0, $c['subtaskTimeTrackingModel']->getUserTimesheet($this->peer));
        $this->assertEquals(0, $c['subtaskTimeTrackingModel']->getTimeSpent($this->subtask, $this->peer));
        $this->assertTrue($c['subtaskModel']->update(array('id' => $this->subtask,'task_id' => $this->task,'title' => 'Renamed','time_spent' => 1)));
        $this->assertEquals(1.5, $c['db']->table('subtasks')->eq('id', $this->subtask)->findOneColumn('time_spent'));
        $this->asUser($this->leader);
        $this->assertCount(2, $c['rvTimeModel']->visible());
        $this->assertCount(1, $c['subtaskTimeTrackingModel']->getUserTimesheet($this->peer));
        $this->assertEquals(1.5, $c['taskFinderModel']->getDetails($this->task)['time_spent']);
        $c['projectUserRoleModel']->removeUser($this->project, $this->leader);
        $this->assertCount(0, $c['rvTimeModel']->visible());
        $this->expectException(DomainException::class);
        $c['rvTimeModel']->readable($this->first);
    }

    public function testForeignSessionAndForeignLeaderDeniedAdminAllowed()
    {
        $c = $this->container;
        $this->asUser($this->member);
        try { $c['rvTimeModel']->history($this->second); $this->fail('Foreign history exposed'); } catch (DomainException $e) { $this->assertTrue(true); }
        $foreign = $c['projectModel']->create(array('name' => 'Foreign'));
        $c['projectUserRoleModel']->removeUser($this->project, $this->leader);
        $c['projectUserRoleModel']->addUser($foreign, $this->leader, Role::PROJECT_MANAGER);
        $this->asUser($this->leader);
        try { $c['rvTimeModel']->review($this->first, true, true, '', 'foreign-review'); $this->fail('Foreign leader approved'); } catch (DomainException $e) { $this->assertTrue(true); }
        $this->asUser(1);
        $this->assertEquals($this->first, $c['rvTimeModel']->review($this->first, false, true, '', 'admin-review'));
    }
}
