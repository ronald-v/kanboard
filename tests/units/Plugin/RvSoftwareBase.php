<?php

namespace KanboardTests\units\Plugin;

use KanboardTests\units\Base;
use Kanboard\Core\Plugin\Loader;

abstract class RvSoftwareBase extends Base
{
    protected $loader;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RVSOFTWARE_TEST') !== 'true') {
            $this->markTestSkipped('Run Invoke-UnitTests.ps1 -Plugin for isolated plugin tests');
        }
        $this->loader = new Loader($this->container);
        $this->loader->initializePlugin('RvSoftware');
        $this->assertArrayHasKey('RvSoftware', $this->loader->getPlugins());
    }
}
