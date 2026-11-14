<?php
namespace KanboardTests\units\Plugin;

use DomainException;
use PDOException;
use Kanboard\Core\Security\Role;

class RvSoftwareBagTest extends RvSoftwareBase
{
    private $project;
    private $leader;

    protected function setUp(): void
    {
        parent::setUp();
        $c = $this->container;
        $this->project = $c['projectModel']->create(array('name'=>'Bag project'));
        $this->leader = $c['userModel']->create(array('username'=>'bag-leader'));
        $c['projectUserRoleModel']->addUser($this->project,$this->leader,Role::PROJECT_MANAGER);
        $c['userSession']->initialize($c['userModel']->getById($this->leader));
    }

    public function testClientBagExtensionArchiveHistoryAndRetries()
    {
        $m = $this->container['rvBagModel'];
        $client = $m->assignClient($this->project,'Synthetic client',0,'client-create');
        $this->assertSame($client,$m->assignClient($this->project,'Synthetic client',0,'client-create'));
        $bag = $m->createBag($this->project,'Contract',36000,null,null,'bag-create');
        $this->assertSame($bag,$m->createBag($this->project,'Contract',36000,null,null,'bag-create'));
        $m->amend($bag,7200,'Extra work','bag-extension');
        $m->amend($bag,7200,'Extra work','bag-extension');
        $this->assertEquals(43200,$m->bags($this->project)[0]['balance_seconds']);
        $this->assertCount(2,$m->entries($bag));
        $m->amend($bag,0,'Completed','bag-archive',true);
        $this->assertEquals(1,$m->bag($bag)['archived']);
        $this->assertCount(2,$m->entries($bag));
        $this->expectException(DomainException::class);
        $m->amend($bag,3600,'Denied','archived-extension');
    }

    public function testScopeAndClientAssignmentAreProtected()
    {
        $c = $this->container; $m = $c['rvBagModel'];
        $m->assignClient($this->project,'Client',0,'client-create');
        $m->createBag($this->project,'Fixed',3600,null,null,'create-fixed');
        try { $m->assignClient($this->project,'Replacement',0,'client-change'); $this->fail('Historical client changed'); } catch (DomainException $e) { $this->assertTrue(true); }
        $foreign = $c['projectModel']->create(array('name'=>'Foreign'));
        try { $m->bags($foreign); $this->fail('Foreign balances exposed'); } catch (DomainException $e) { $this->assertTrue(true); }
        $c['projectUserRoleModel']->changeUserRole($this->project,$this->leader,Role::PROJECT_MEMBER);
        $this->expectException(DomainException::class);
        $m->bags($this->project);
    }

    public function testValidityAndOperationPayloadAreValidated()
    {
        $m = $this->container['rvBagModel'];
        $m->assignClient($this->project,'Client',0,'client-create');
        foreach (array(array(-1,null,null),array(1,200,100),array(1,'invalid',null)) as $values) {
            try { $m->createBag($this->project,'Invalid',$values[0],$values[1],$values[2],'invalid-bag'); $this->fail('Invalid bag accepted'); } catch (DomainException $e) { $this->assertTrue(true); }
        }
        $bag = $m->createBag($this->project,'Valid',3600,100,200,'valid-bag');
        $this->assertEquals(100,$m->bag($bag)['valid_from']);
        $this->expectException(DomainException::class);
        $m->createBag($this->project,'Valid',7200,100,200,'valid-bag');
    }

    public function testProjectDeletionCannotEraseCommercialHistory()
    {
        $this->container['rvBagModel']->assignClient($this->project,'Client',0,'client-create');
        $this->expectException(PDOException::class);
        $this->container['db']->getConnection()->prepare('DELETE FROM projects WHERE id=?')->execute(array($this->project));
    }
}
