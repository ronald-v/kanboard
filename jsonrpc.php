<?php

// Bound unauthenticated input before bootstrap/JSON parsing allocates the payload.
if (($_SERVER['REQUEST_METHOD'] ?? 'POST')!=='POST') {
    http_response_code(405); header('Allow: POST'); header('Content-Type: application/json');
    echo '{"error":"POST required"}'; return;
}
$projectaInput=file_get_contents('php://input',false,null,0,262145);
if (strlen($projectaInput)>262144 || (int)($_SERVER['CONTENT_LENGTH'] ?? 0)>262144) {
    http_response_code(413); header('Content-Type: application/json'); echo '{"error":"Request too large"}'; return;
}
require __DIR__.'/app/common.php';

if (isset($container['rvApiModel']) && ! $container['rvApiModel']->preflight()) { return; }
try {
    $result=$container['api']->execute();
    if (isset($container['rvApiModel'])) {
        header('Cache-Control: no-store');
        echo $container['rvApiModel']->response($result);
    } else { echo $result; }
} catch (\Throwable $error) {
    if (! isset($container['rvApiModel'])) { throw $error; }
    $container['response']->json(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32603,'message'=>'Internal error']],500);
}
