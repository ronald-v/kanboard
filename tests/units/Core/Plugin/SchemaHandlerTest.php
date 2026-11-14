<?php

namespace KanboardTests\units\Core\Plugin;

use KanboardTests\units\Base;
use Kanboard\Core\Plugin\SchemaHandler;

class SchemaHandlerTest extends Base
{
    public function testCurrentAndNewerSchemasOnlyReadTheirVersion()
    {
        require_once __DIR__.'/../../../fixtures/PluginSchema.php';
        $db = $this->createMock(\PicoDb\Database::class);
        $db->expects($this->never())->method('startTransaction');
        $db->expects($this->never())->method('getDriver');
        $container = new \Pimple\Container(array('db'=>$db));
        $handler = $this->getMockBuilder(SchemaHandler::class)
            ->setConstructorArgs(array($container))->onlyMethods(array('getSchemaVersion','setSchemaVersion'))->getMock();
        $handler->expects($this->exactly(2))->method('getSchemaVersion')->with('HarnessSchema')->willReturn(2,3);
        $handler->expects($this->never())->method('setSchemaVersion');
        $handler->migrateSchema('HarnessSchema');
        $handler->migrateSchema('HarnessSchema');
    }

    public function testFreshInstallationAndRepeatedRequest()
    {
        require_once __DIR__.'/../../../fixtures/PluginSchema.php';
        $handler = new SchemaHandler($this->container);
        $handler->migrateSchema('HarnessSchema');
        $this->assertEquals(2, $handler->getSchemaVersion('HarnessSchema'));
        $this->assertEquals(2, $this->container['db']->table('harness_schema_probe')->eq('id',1)->findOneColumn('value'));
        $handler->migrateSchema('HarnessSchema');
        $this->assertEquals(1, $this->container['db']->table('harness_schema_probe')->count());
        $this->assertFalse($this->container['db']->getConnection()->inTransaction());
    }

    public function testPendingUpgradeAndNewerVersionArePreserved()
    {
        require_once __DIR__.'/../../../fixtures/PluginSchema.php';
        \Kanboard\Plugin\HarnessSchema\Schema\version_1($this->container['db']->getConnection());
        $handler = new SchemaHandler($this->container);
        $handler->setSchemaVersion('HarnessSchema',1);
        $handler->migrateSchema('HarnessSchema');
        $this->assertEquals(2, $handler->getSchemaVersion('HarnessSchema'));
        $this->assertEquals(2, $this->container['db']->table('harness_schema_probe')->eq('id',1)->findOneColumn('value'));
        $handler->setSchemaVersion('HarnessSchema',3);
        $handler->migrateSchema('HarnessSchema');
        $this->assertEquals(3, $handler->getSchemaVersion('HarnessSchema'));
    }

    public function testFailureRollsBackAndRestoresForeignKeyChecks()
    {
        require_once __DIR__.'/../../../fixtures/PluginSchema.php';
        $pdo = $this->container['db']->getConnection();
        $pdo->exec('CREATE TABLE harness_schema_failure (id INTEGER NOT NULL PRIMARY KEY)');
        $handler = new SchemaHandler($this->container);
        try {
            $handler->migrateSchema('HarnessFailure');
            $this->fail('Migration must fail');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Synthetic migration failure', $error->getMessage());
        }
        $this->assertEquals(0, $handler->getSchemaVersion('HarnessFailure'));
        $this->assertEquals(0, $this->container['db']->table('harness_schema_failure')->count());
        $this->assertFalse($pdo->inTransaction());
        if (DB_DRIVER === 'mysql') { $this->assertEquals(1, $pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn()); }
        elseif (DB_DRIVER === 'sqlite') { $this->assertEquals(1, $pdo->query('PRAGMA foreign_keys')->fetchColumn()); }
    }

    public function testGetSchemaVersion()
    {
        $p = new SchemaHandler($this->container);
        $this->assertEquals(0, $p->getSchemaVersion('not_found'));

        $this->assertTrue($p->setSchemaVersion('plugin1', 1));
        $this->assertEquals(1, $p->getSchemaVersion('plugin1'));

        $this->assertTrue($p->setSchemaVersion('plugin2', 33));
        $this->assertEquals(33, $p->getSchemaVersion('plugin2'));

        $this->assertTrue($p->setSchemaVersion('plugin1', 2));
        $this->assertEquals(2, $p->getSchemaVersion('plugin1'));
    }
}
