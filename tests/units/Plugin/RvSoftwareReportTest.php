<?php
namespace KanboardTests\units\Plugin;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Kanboard\Core\Security\Role;

class RvSoftwareReportTest extends RvSoftwareBase
{
    private $project;
    private $member;
    private $first;
    private $bag;

    protected function setUp(): void
    {
        parent::setUp(); $c = $this->container;
        $this->project = $c['projectModel']->create(array('name'=>'=Synthetic project'));
        $task = $c['taskCreationModel']->create(array('title'=>'+Synthetic task','project_id'=>$this->project));
        $subtask = $c['subtaskModel']->create(array('title'=>'@Source','task_id'=>$task));
        $this->member = $c['userModel']->create(array('username'=>'report-member'));
        $peer = $c['userModel']->create(array('username'=>'report-peer'));
        foreach (array($this->member,$peer) as $id) { $c['projectUserRoleModel']->addUser($this->project,$id,Role::PROJECT_MEMBER); }
        $c['userSession']->initialize($c['userModel']->getById($this->member));
        $end = (new DateTimeImmutable('2026-10-01 00:00:20',new DateTimeZone('America/Bogota')))->getTimestamp();
        $this->first = $c['rvTimeModel']->manual($subtask,$end-40,$end,'=SUM(1,2)','report-midnight');
        $second = $c['rvTimeModel']->manual($subtask,$end+3600,$end+7200,'Internal','report-internal');
        $c['userSession']->initialize($c['userModel']->getById($peer));
        $c['rvTimeModel']->manual($subtask,$end+7200,$end+9000,'Peer note','report-peer-work');
        $c['userSession']->initialize($c['userModel']->getById(1));
        $c['rvBagModel']->assignClient($this->project,'-Client',0,'report-client');
        $this->bag = $c['rvBagModel']->createBag($this->project,'Bag',7200,null,null,'report-bag');
        $c['rvTimeModel']->review($this->first,true,true,'Approved','report-approve',$this->bag);
        $c['rvTimeModel']->review($second,false,true,'Internal','report-internal-review');
    }

    public function testWorkedDateClippingAndIndependentTotals()
    {
        $m = $this->container['rvReportModel'];
        $report = $m->report('2026-10-01','2026-10-01');
        $this->assertSame(array('worked_seconds'=>5420,'billable_seconds'=>20,'approved_seconds'=>3620,'consumed_seconds'=>20,'unreconciled_sessions'=>0),$report['totals']);
        $previous = $m->report('2026-09-30','2026-09-30');
        $both = $m->report('2026-09-30','2026-10-01');
        $this->assertEquals(20,$previous['totals']['worked_seconds']);
        $this->assertEquals($both['totals']['worked_seconds'],$report['totals']['worked_seconds']+$previous['totals']['worked_seconds']);
        $this->assertEquals(40,$both['totals']['consumed_seconds']);
        $this->assertCount(1,$m->report('2026-10-01','2026-10-01',array('billable'=>'1','status'=>'approved','bag_id'=>$this->bag))['rows']);
    }

    public function testMemberScopeAndCsvInjectionDefense()
    {
        $c = $this->container;
        $csv = $c['rvReportModel']->csv($c['rvReportModel']->report('2026-10-01','2026-10-01'));
        $this->assertSame("'=Synthetic project",$csv[1][2]);
        $this->assertSame("'=SUM(1,2)",$csv[1][16]);
        $this->assertSame("'\t=FORMULA()",$c['rvReportModel']->safeCell("\t=FORMULA()"));
        $c['userSession']->initialize($c['userModel']->getById($this->member));
        $report = $c['rvReportModel']->report('2026-10-01','2026-10-01');
        $this->assertCount(2,$report['rows']);
        $this->assertEquals(3620,$report['totals']['worked_seconds']);
        $this->assertNull($report['rows'][0]['client_name']);
        $this->assertNull($report['rows'][0]['bag_name']);
        $this->expectException(DomainException::class);
        $c['rvReportModel']->report('2026-10-01','2026-10-01',array('bag_id'=>$this->bag));
    }

    public function testCorrectionChangesCurrentReportAndPreservesAudit()
    {
        $c = $this->container; $row = $c['rvTimeModel']->session($this->first);
        $c['rvTimeModel']->rectify($this->first,$row['end']-60,$row['end'],true,'Retro correction','Correct start','report-rectify');
        $this->assertEquals(40,$c['rvReportModel']->report('2026-09-30','2026-09-30')['totals']['consumed_seconds']);
        $this->assertCount(3,$c['rvTimeModel']->history($this->first));
        $this->assertCount(0,$c['rvLedgerModel']->reconcile($this->project)['differences']);
    }

    public function testInvalidDatesAndRevokedMembership()
    {
        $c = $this->container;
        foreach (array(array('2026-02-30','2026-10-01'),array('2026-10-02','2026-10-01'),array('invalid','2026-10-01')) as $dates) {
            try { $c['rvReportModel']->report($dates[0],$dates[1]); $this->fail('Invalid date accepted'); } catch (DomainException $e) { $this->assertTrue(true); }
        }
        $c['userSession']->initialize($c['userModel']->getById($this->member));
        $c['projectUserRoleModel']->removeUser($this->project,$this->member);
        $this->assertCount(0,$c['rvReportModel']->report('2026-10-01','2026-10-01')['rows']);
    }
}
