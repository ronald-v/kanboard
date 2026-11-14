<?php
namespace KanboardTests\units\Plugin;

class RvSoftwarePolicyTest extends RvSoftwareBase
{
    public function testDisabledRoutesAndRetainedNativeFeatures()
    {
        $m=$this->container['rvPolicyModel'];
        foreach ([['CurrencyController','show'],['PluginController','show'],['ConfigController','integrations'],['ConfigController','webhook'],['BoardViewController','readonly'],['UserViewController','external']] as $route) { $this->assertTrue($m->disabled(...$route)); }
        foreach ([['TaskViewController','show'],['GroupListController','index'],['UserListController','show'],['ConfigController','application']] as $route) { $this->assertFalse($m->disabled(...$route)); }
        $m->validateSettings(['application_language'=>'es_ES','project_categories'=>'Development']);
        $this->expectException(\Kanboard\Core\Controller\AccessForbiddenException::class);
        $m->validateSettings(['mail_transport'=>'mail']);
    }

    public function testUnconfiguredSmtpNeverFallsBackToPhpMail()
    {
        $client=$this->container['emailClient'];
        $this->assertFalse((bool)$client->ready());
        $this->assertSame(['smtp'=>'smtp'],$client->getAvailableTransports());
        $this->assertSame($client,$client->send('synthetic@example.invalid','Synthetic','Test','No delivery'));
        $this->assertSame('Projecta',$client->getAuthorName());
    }
}
