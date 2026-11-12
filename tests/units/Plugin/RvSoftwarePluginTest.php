<?php

namespace KanboardTests\units\Plugin;

use Kanboard\Core\Plugin\SchemaHandler;
use Kanboard\Core\Security\Role;

class RvSoftwarePluginTest extends RvSoftwareBase
{
    public function testLoadVersionAndIdempotentSchema()
    {
        $plugin = $this->loader->getPlugins()['RvSoftware'];
        $this->assertEquals('0.1.0', $plugin->getPluginVersion());
        $schema = new SchemaHandler($this->container);
        $this->assertEquals(1, $schema->getSchemaVersion('RvSoftware'));
        $schema->loadSchema('RvSoftware');
        $this->assertEquals(1, $schema->getSchemaVersion('RvSoftware'));
        $this->assertEquals(1, $this->container['db']->table('plugin_schema_versions')->eq('plugin', 'rvsoftware')->count());
    }

    public function testRouteAccessRequiresAuthenticatedRole()
    {
        $authorization = $this->container['applicationAuthorization'];
        $this->assertFalse($authorization->isAllowed('RvHomeController', 'show', Role::APP_PUBLIC));
        $this->assertTrue($authorization->isAllowed('RvHomeController', 'show', Role::APP_USER));
        $this->assertTrue($authorization->isAllowed('RvHomeController', 'show', Role::APP_ADMIN));
        $this->assertTrue($this->container['hook']->exists('template:layout:css'));
    }
}
