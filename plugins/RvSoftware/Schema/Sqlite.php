<?php

namespace Kanboard\Plugin\RvSoftware\Schema;

const VERSION = 1;

function version_1($pdo)
{
    // Baseline only: native plugin_schema_versions records this version.
    // Domain tables belong to later features, not plugin initialization.
}
