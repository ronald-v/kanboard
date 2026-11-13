<?php
if (getenv('DB_HOSTNAME') !== '127.0.0.1' || getenv('DB_PORT') !== '13318' || getenv('DB_NAME') !== 'kanboard_compat_http') { exit(2); }
require __DIR__.'/../../app/common.php';
$pdo = $container['db']->getConnection();
$version = $pdo->query('SELECT VERSION()')->fetchColumn();
$schema = $pdo->query("SELECT version FROM plugin_schema_versions WHERE plugin='rvsoftware'")->fetchColumn();
if (! str_starts_with($version,'11.8.9-MariaDB') || (int)$schema !== 6) { throw new RuntimeException('Unexpected target schema'); }
echo 'PASS fresh native/plugin migration: PHP '.PHP_VERSION.'; '.$version.'; plugin schema '.$schema.PHP_EOL;
