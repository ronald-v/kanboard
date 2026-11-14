<?php
// Opt-in loopback router. No load generator, request bodies, SQL, IDs or cookies.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || ! getenv('PROJECTA_LOCAL_PROFILE_FILE')) {
    http_response_code(404); exit;
}
$root = dirname(__DIR__, 2);
$appRoot = getenv('PROJECTA_PROFILE_APP_ROOT') ?: $root;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && $path !== '/index.php') {
    if (preg_match('~^/(assets/|plugins/RvSoftware/Assets/)[a-zA-Z0-9_./-]+$~D', $path) && ! str_contains($path, '..')) { return false; }
    http_response_code(404); exit;
}
$started = hrtime(true);
ob_start();
try {
    if (realpath($appRoot) !== realpath($root)) {
        // Comparison of normal activity: definitions stay in process memory;
        // private configuration and real data are never copied into the baseline.
        if (realpath($appRoot) !== realpath($root.'/.runtime/f019/before/app')) { throw new RuntimeException(); }
        foreach (['/data/config.php','/config.php'] as $config) { if (is_file($root.$config)) { require $root.$config; } }
        defined('DATA_DIR') or define('DATA_DIR', getenv('DATA_DIR') ?: $root.'/data');
        if (defined('PLUGINS_DIR') && realpath(PLUGINS_DIR) !== realpath($appRoot.'/plugins')) { throw new RuntimeException(); }
        defined('PLUGINS_DIR') or define('PLUGINS_DIR', $appRoot.'/plugins');
    }
    require $appRoot.'/app/common.php';
    $boot = (hrtime(true)-$started)/1e6;
    $container['router']->dispatch();
    (new \Kanboard\Core\Controller\Runner($container))->execute();
    $elapsed = (hrtime(true)-$started)/1e6;
    $at = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format(DATE_ATOM);
    $row = ['at'=>$at, 'variant'=>getenv('PROJECTA_PROFILE_VARIANT') ?: 'Current', 'operation'=>$container['router']->getController().'/'.$container['router']->getAction(),
        'status'=>http_response_code() ?: 200, 'php_ms'=>round($elapsed,2), 'bootstrap_ms'=>round($boot,2),
        'pico_queries'=>$container['db']->getStatementHandler()->getNbQueries()];
    // No extra SQL probe on the real connection: one remote round trip itself
    // costs ~117 ms. Total SQL counters (including PDO) belong to synthetic runs.
    header('Server-Timing: app;dur='.$row['php_ms'].', bootstrap;dur='.$row['bootstrap_ms']);
    header('X-Projecta-Operation: '.$row['operation']);
    header('X-Projecta-Profile-Variant: '.$row['variant']);
    // The launch script chooses this private file outside the served tree.
    file_put_contents(getenv('PROJECTA_LOCAL_PROFILE_FILE'), json_encode($row).PHP_EOL, FILE_APPEND | LOCK_EX);
} catch (Throwable $error) {
    http_response_code(500);
    echo 'No se pudo completar la solicitud.';
}
ob_end_flush();
