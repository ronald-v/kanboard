<?php
namespace KanboardTests\units\Plugin;

class RvSoftwareApiTest extends RvSoftwareBase
{
    private function actor()
    {
        $c=$this->container;
        $id=$c['userModel']->create(['username'=>'api-unit','password'=>'synthetic-password']);
        $c['userSession']->initialize($c['userModel']->getById($id));
        $secret=\Otp\GoogleAuthenticator::generateRandom();
        $c['rvSecurityModel']->enroll($id,$secret,(new \Otp\Otp)->totp(\Base32\Base32::decode($secret)));
        return $id;
    }

    public function testTokenHashScopeAndRevocation()
    {
        $id=$this->actor(); $m=$this->container['rvApiModel'];
        $token=$m->issue($id,'Read-only integration',['read']);
        $rows=$m->tokens($id);
        $this->assertCount(1,$rows);
        $this->assertNotSame($token,$rows[0]['token_hash']);
        $m->authenticate('api-unit',$token);
        $m->authorizeMethod('getTask');
        try { $m->authorizeMethod('createTask'); $this->fail('Read scope wrote'); } catch (\JsonRPC\Exception\AccessDeniedException $error) { $this->assertTrue(true); }
        $m->revoke($id,hash('sha256',$token));
        $this->expectException(\JsonRPC\Exception\AuthenticationFailureException::class);
        $m->authenticate('api-unit',$token);
    }

    public function testPasswordChangeAndExpiryInvalidateCredentials()
    {
        $id=$this->actor(); $c=$this->container; $m=$c['rvApiModel'];
        $token=$m->issue($id,'Password invalidation');
        $c['userModel']->update(['id'=>$id,'password'=>'changed-synthetic-password']);
        try { $m->authenticate('api-unit',$token); $this->fail('Old password token valid'); } catch (\JsonRPC\Exception\AuthenticationFailureException $error) { $this->assertTrue(true); }
        $token=$m->issue($id,'Expired');
        $m->sql('UPDATE rv_api_tokens SET expires_at=?',[time()-1]);
        $this->expectException(\JsonRPC\Exception\AuthenticationFailureException::class);
        $m->authenticate('api-unit',$token);
    }

    public function testDeletionRejectsHistoryBeforeRemovingAttachmentsOrRows()
    {
        $c=$this->container;
        $c['userSession']->initialize($c['userModel']->getById(1));
        $project=$c['projectModel']->create(['name'=>'History protected']);
        $task=$c['taskCreationModel']->create(['title'=>'Protected','project_id'=>$project]);
        $subtask=$c['subtaskModel']->create(['title'=>'Work','task_id'=>$task]);
        $c['rvTimeModel']->manual($subtask,1700000000,1700003600,'Synthetic history','protected-history');
        foreach ([['subtaskModel',$subtask],['taskModel',$task],['projectModel',$project]] as $operation) {
            try { $c[$operation[0]]->remove($operation[1]); $this->fail('History removed'); } catch (\DomainException $error) { $this->assertStringStartsWith('HISTORY_CONFLICT:',$error->getMessage()); }
        }
        $empty=$c['subtaskModel']->create(['title'=>'No history','task_id'=>$task]);
        $this->assertTrue($c['subtaskModel']->remove($empty));
        $this->assertNotEmpty($c['subtaskModel']->getById($subtask));
        $this->assertNotEmpty($c['taskFinderModel']->getById($task));
    }
}
