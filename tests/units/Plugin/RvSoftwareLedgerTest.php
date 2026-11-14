<?php
namespace KanboardTests\units\Plugin;

use DomainException;
use Kanboard\Core\Security\Role;

class RvSoftwareLedgerTest extends RvSoftwareBase
{
    private $project;
    private $subtask;
    private $bag;
    private $end;

    protected function setUp(): void
    {
        parent::setUp();
        $c = $this->container;
        $c['userSession']->initialize($c['userModel']->getById(1));
        $this->project = $c['projectModel']->create(array('name'=>'Ledger project'));
        $task = $c['taskCreationModel']->create(array('title'=>'Ledger task','project_id'=>$this->project));
        $this->subtask = $c['subtaskModel']->create(array('title'=>'Ledger source','task_id'=>$task));
        $c['rvBagModel']->assignClient($this->project,'Client',0,'ledger-client');
        $this->bag = $c['rvBagModel']->createBag($this->project,'Bag',1800,null,null,'ledger-bag');
        $this->end = time()-60;
    }

    private function record($seconds = 3600)
    {
        return $this->container['rvTimeModel']->manual($this->subtask,$this->end-$seconds,$this->end,'Synthetic','ledger-record');
    }

    public function testApprovalCompensationAndBalanceReconcile()
    {
        $c = $this->container; $m = $c['rvTimeModel'];
        $id = $this->record();
        $this->assertEquals(1800,$c['rvBagModel']->bags($this->project)[0]['balance_seconds']);
        $m->review($id,true,true,'Approve','ledger-approve',$this->bag);
        $m->review($id,true,true,'Approve','ledger-approve',$this->bag);
        $this->assertEquals(-1800,$c['rvBagModel']->bags($this->project)[0]['balance_seconds']);
        $this->assertCount(2,$c['rvBagModel']->entries($this->bag));
        $m->rectify($id,$this->end-1200,$this->end,true,'Shorter','Correction','ledger-rectify');
        $this->assertEquals(600,$c['rvBagModel']->bags($this->project)[0]['balance_seconds']);
        $m->rectify($id,$this->end-1200,$this->end,false,'Internal','Reclassify','ledger-reclass');
        $this->assertEquals(1800,$c['rvBagModel']->bags($this->project)[0]['balance_seconds']);
        $entries = $c['rvBagModel']->entries($this->bag);
        $this->assertEquals(array(1800,-3600,2400,1200),array_map('intval',array_column($entries,'seconds')));
        $this->assertCount(0,$c['rvLedgerModel']->reconcile($this->project)['differences']);
        $adjust = $c['rvLedgerModel']->adjust($this->bag,-300,'Audit adjustment','ledger-adjust');
        $this->assertSame($adjust,$c['rvLedgerModel']->adjust($this->bag,-300,'Audit adjustment','ledger-adjust'));
        $this->assertEquals(1500,$c['rvBagModel']->bags($this->project)[0]['balance_seconds']);
    }

    public function testMissingBagAndExpiredWorkRollBackApproval()
    {
        $c = $this->container; $m = $c['rvTimeModel']; $id = $this->record();
        try { $m->review($id,true,true,'Missing bag','missing-bag'); $this->fail('Approval without bag'); } catch (DomainException $e) { $this->assertSame('pending',$m->session($id)['status']); }
        $expired = $c['rvBagModel']->createBag($this->project,'Old',3600,100,200,'expired-bag');
        try { $m->review($id,true,true,'Expired','expired-approval',$expired); $this->fail('Expired work accepted'); } catch (DomainException $e) { $this->assertSame('pending',$m->session($id)['status']); }
        $this->assertCount(1,$m->history($id));
        $this->assertCount(1,$c['rvBagModel']->entries($expired));
    }

    public function testAuditFailureRollsBackLedgerAndApprovalTogether()
    {
        $c = $this->container; $id = $this->record();
        $model = new class($c) extends \Kanboard\Plugin\RvSoftware\Model\RvTimeModel {
            public function audit($id,$action,$before,$after,$reason,$key,$hash = '') { throw new \RuntimeException('Injected failure'); }
        };
        try { $model->review($id,true,true,'Approve','rollback-ledger',$this->bag); $this->fail('Injected failure ignored'); } catch (\RuntimeException $e) {
            $this->assertSame('pending',$c['rvTimeModel']->session($id)['status']);
            $this->assertCount(1,$c['rvBagModel']->entries($this->bag));
            $this->assertCount(1,$c['rvTimeModel']->history($id));
        }
    }

    public function testUnallocatedHistoricalApprovalIsReportedAndExplicitlyReconciled()
    {
        $c = $this->container; $id = $this->record();
        // State from F-008 before ledger support; migration must not invent a bag.
        $c['db']->table('rv_time_meta')->eq('session_id',$id)->update(array('status'=>'approved','billable'=>1));
        $this->assertCount(1,$c['rvLedgerModel']->reconcile($this->project)['differences']);
        $c['rvTimeModel']->rectify($id,$this->end-3600,$this->end,true,'Same work','Explicit historical allocation','history-allocation',$this->bag);
        $this->assertCount(0,$c['rvLedgerModel']->reconcile($this->project)['differences']);
        $this->assertEquals(-1800,$c['rvBagModel']->bags($this->project)[0]['balance_seconds']);
    }
}
