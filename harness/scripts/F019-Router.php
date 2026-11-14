<?php
// Harness-only router. Fixed synthetic target, aggregate counters, no SQL/values.
if (getenv('F019_PROFILE') !== 'true' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' ||
    getenv('DB_HOSTNAME') !== '127.0.0.1' || getenv('DB_PORT') !== '13318' || getenv('DB_NAME') !== 'kanboard_compat_http') {
    http_response_code(404); exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && $path !== '/index.php') {
    if (preg_match('~^/(assets/|plugins/RvSoftware/Assets/)[a-zA-Z0-9_./-]+$~D', $path) && ! str_contains($path, '..')) { return false; }
    http_response_code(404); exit;
}
$start = hrtime(true);
ob_start();
require getenv('F019_APP').'/app/common.php';
$bootstrap = (hrtime(true)-$start)/1e6;
$container['router']->dispatch();
$runner = new \Kanboard\Core\Controller\Runner($container);
$runner->execute();
$total = (hrtime(true)-$start)/1e6;
$status = $container['db']->getConnection()->query("SHOW SESSION STATUS WHERE Variable_name IN ('Questions','Com_select','Com_insert','Com_replace','Com_update','Com_begin','Com_commit','Com_set_option')")->fetchAll(PDO::FETCH_KEY_PAIR);
$status['Questions'] = max(0, (int)$status['Questions']-1); // exclude this status probe
header('Server-Timing: app;dur='.round($total,2).', bootstrap;dur='.round($bootstrap,2).', db-requests;dur='.$status['Questions']);
header('X-F019-Db-Requests: '.$status['Questions']);
header('X-F019-Pico-Queries: '.$container['db']->getStatementHandler()->getNbQueries());
header('X-F019-Db-Writes: '.((int)$status['Com_insert']+(int)$status['Com_update']+(int)$status['Com_replace']));
ob_end_flush();
