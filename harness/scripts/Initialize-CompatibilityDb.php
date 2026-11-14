<?php
// This bootstrap knows only the disposable, loopback instance created for F-012.
$pdo = new PDO('mysql:host=127.0.0.1;port=13318','root','synthetic-local-only',array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
$server = $pdo->query('SELECT VERSION() AS version,@@port AS port,@@datadir AS datadir')->fetch(PDO::FETCH_ASSOC);
$expected = realpath(__DIR__.'/../../.runtime/compatibility/maria-data');
if (! $expected || realpath($server['datadir']) !== $expected || (int)$server['port'] !== 13318 || ! str_starts_with($server['version'],'11.8.9-MariaDB')) {
    throw new RuntimeException('Refusing to initialize an unexpected database instance');
}
foreach (array('kanboard_unit_test','kanboard_compat_http','kanboard_compat_restore') as $database) {
    $pdo->exec('CREATE DATABASE IF NOT EXISTS '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
$pdo->exec("CREATE USER IF NOT EXISTS 'compat_synthetic'@'127.0.0.1' IDENTIFIED BY 'synthetic-local-only'");
foreach (array('kanboard_unit_test','kanboard_compat_http','kanboard_compat_restore') as $database) {
    $pdo->exec("GRANT ALL PRIVILEGES ON $database.* TO 'compat_synthetic'@'127.0.0.1'");
}
echo 'PASS isolated MariaDB identity: '.$server['version'].'; port 13318; dedicated schemas only'.PHP_EOL;
