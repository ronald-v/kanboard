<?php
namespace KanboardTests\units\Plugin;

use Otp\Otp;
use Base32\Base32;

class RvSoftwareSecurityTest extends RvSoftwareBase
{
    private function user()
    {
        $id=$this->container['userModel']->create(['username'=>'security-user','password'=>'synthetic-password']);
        $this->container['userSession']->initialize($this->container['userModel']->getById($id));
        return $id;
    }

    public function testEncryptedEnrollmentReplayAndSingleUseRecovery()
    {
        $id=$this->user(); $m=$this->container['rvSecurityModel'];
        $secret=\Otp\GoogleAuthenticator::generateRandom();
        $code=(new Otp)->totp(Base32::decode($secret));
        $codes=$m->enroll($id,$secret,$code);
        $factor=$m->factor($id);
        $this->assertNotSame($secret,$factor['secret']);
        $this->assertSame($secret,$m->unseal($factor['secret']));
        $this->assertFalse($m->verify($id,$code),'Enrollment code cannot be replayed');
        $this->assertCount(10,$codes);
        $this->assertTrue($m->verify($id,$codes[0]));
        $this->assertFalse($m->verify($id,$codes[0]));
        $this->assertFalse($m->verify($id,str_repeat('x',32)));
        $this->assertSame('', $this->container['userModel']->getById($id)['twofactor_secret']);
    }

    public function testLimiterPersistsNormalizesAndExpires()
    {
        $m=$this->container['rvSecurityModel'];
        $this->assertSame(1,$m->consume('test','USER',2,60));
        $this->assertSame(2,$m->consume('test','user',2,60));
        try { $m->consume('test','User',2,60); $this->fail('Limit bypassed'); } catch (\DomainException $error) { $this->assertTrue(true); }
        $m->sql('UPDATE rv_auth_limits SET expires_at=?',[time()-1]);
        $this->assertSame(1,$m->consume('test','user',2,60));
        $m->clear('test','User');
        $this->assertSame(1,$m->consume('test','user',2,60));
    }

    public function testResetInvalidatesRecoveryAndFactorGeneration()
    {
        $id=$this->user(); $m=$this->container['rvSecurityModel'];
        $secret=\Otp\GoogleAuthenticator::generateRandom();
        $codes=$m->enroll($id,$secret,(new Otp)->totp(Base32::decode($secret)));
        $this->container['rvAccessModel']->passwordAccepted();
        $this->container['rvAccessModel']->markValidated();
        $this->assertNotEmpty(session_get('rv_mfa_generation'));
        $m->resetFactor($id);
        $this->assertFalse($m->factor($id));
        $this->assertFalse($m->verify($id,$codes[0]));
        $this->assertEquals(0,$this->container['userModel']->getById($id)['twofactor_activated']);
    }

    public function testExistingNativeFactorMigratesWithoutGivingAnEnrollmentBypass()
    {
        $id=$this->user(); $c=$this->container; $m=$c['rvSecurityModel'];
        $secret=\Otp\GoogleAuthenticator::generateRandom();
        $c['db']->table('users')->eq('id',$id)->update(['twofactor_activated'=>1,'twofactor_secret'=>$secret]);
        $factor=$m->factor($id);
        $this->assertSame($secret,$m->unseal($factor['secret']));
        $this->assertSame('',$c['userModel']->getById($id)['twofactor_secret']);
        $this->assertTrue($m->verify($id,(new Otp)->totp(Base32::decode($secret))));
    }

    public function testIdleAndAbsoluteExpiryCloseTheSession()
    {
        $this->user(); $c=$this->container;
        $manager=$this->createMock(\Kanboard\Core\Session\SessionManager::class);
        $manager->expects($this->exactly(2))->method('close');
        $c['sessionManager']=$manager;
        $response=$this->createMock(\Kanboard\Core\Http\Response::class);
        $response->expects($this->exactly(2))->method('redirect');
        $c['response']=$response;
        session_set('rv_started_at',time()); session_set('rv_last_at',time()-1801);
        $this->assertFalse($c['rvAccessModel']->guard());
        session_set('rv_started_at',time()-28801); session_set('rv_last_at',time());
        $this->assertFalse($c['rvAccessModel']->guard());
    }
}
