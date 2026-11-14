<?php
// Read-only preflight. Does not load common.php, authenticate, or run migrations.
// Print aggregate timings/schema numbers only; never emit a PDO/config exception.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$schemaRoot = ($argv[1] ?? '') === '--baseline' ? $root.'/.runtime/f019/before/app' : $root;
$stage = 'configuration';
ob_start();
try {
    foreach (['/data/config.php', '/config.php'] as $file) {
        if (is_file($root.$file)) { require $root.$file; }
    }
    require_once $root.'/app/functions.php';
    require $root.'/app/constants.php';
    if (DB_DRIVER !== 'mysql') { throw new RuntimeException(); }
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10];
    foreach (['SSL_KEY'=>DB_SSL_KEY, 'SSL_CERT'=>DB_SSL_CERT, 'SSL_CA'=>DB_SSL_CA, 'SSL_VERIFY_SERVER_CERT'=>DB_VERIFY_SERVER_CERT] as $key=>$value) {
        if ($value !== null) { $options[constant(defined('Pdo\\Mysql::ATTR_'.$key) ? 'Pdo\\Mysql::ATTR_'.$key : 'PDO::MYSQL_ATTR_'.$key)] = $value; }
    }
    $stage = 'connection';
    $start = hrtime(true);
    $pdo = new PDO('mysql:host='.DB_HOSTNAME.';port='.(DB_PORT ?: 3306).';dbname='.DB_NAME, DB_USERNAME, DB_PASSWORD, $options);
    $result = ['connection_ms'=>round((hrtime(true)-$start)/1e6, 2), 'select_one_ms'=>[]];
    $stage = 'read-only queries';
    $pdo->exec('START TRANSACTION READ ONLY');
    for ($i=0; $i<3; $i++) {
        $start = hrtime(true);
        $pdo->query('SELECT 1')->fetchColumn();
        $result['select_one_ms'][] = round((hrtime(true)-$start)/1e6, 2);
    }
    $result['native_schema'] = (int)$pdo->query('SELECT version FROM schema_version LIMIT 1')->fetchColumn();
    $result['plugin_schema'] = (int)$pdo->query("SELECT version FROM plugin_schema_versions WHERE plugin='rvsoftware'")->fetchColumn();
    $pdo->rollBack();
    require $schemaRoot.'/app/Schema/Mysql.php';
    require $schemaRoot.'/plugins/RvSoftware/Schema/Mysql.php';
    $result['expected_native_schema'] = \Schema\VERSION;
    $result['expected_plugin_schema'] = \Kanboard\Plugin\RvSoftware\Schema\VERSION;
    $result['application_boot_allowed'] = $result['native_schema'] === $result['expected_native_schema'] && $result['plugin_schema'] === $result['expected_plugin_schema'];
    ob_end_clean();
    echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $error) {
    ob_end_clean();
    fwrite(STDERR, "Read-only preflight failed at ".$stage."; details withheld. No application bootstrap performed.\n");
    exit(2);
}
