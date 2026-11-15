<?php
// The parent runner verifies the synthetic datadir before invoking fixtures.
if (PHP_SAPI !== 'cli' || getenv('DB_HOSTNAME') !== '127.0.0.1' || getenv('DB_PORT') !== '13318' || getenv('DB_NAME') !== 'kanboard_compat_http') { exit(2); }
require __DIR__.'/../../.runtime/projecta/app/app/common.php';
$fixture = json_decode(file_get_contents(__DIR__.'/../../.runtime/projecta/api-fixture.json'), true);
$container['userSession']->initialize($container['userModel']->getById($fixture['users']['manager']['id']));
$blob = 'F020 synthetic authorized attachment';
$path = 'projects/'.$fixture['project_id'].'/f020-private.txt';
$container['objectStorage']->put($path, $blob);
$id = $container['projectFileModel']->create($fixture['project_id'], 'f020-private.txt', $path, strlen($blob));
$fixture['f020_file_id'] = $id;
$large = 'projects/'.$fixture['project_id'].'/f020-large.bin';
$target = FILES_DIR.'/'.$large;
if (!str_starts_with(str_replace('\\', '/', realpath(dirname($target))).'/', str_replace('\\', '/', realpath(__DIR__.'/../../.runtime/projecta/data/files')).'/')) { exit(2); }
$stream = fopen($target, 'wb');
ftruncate($stream, 80 * 1024 * 1024);
fclose($stream);
$fixture['f020_large_id'] = $container['projectFileModel']->create($fixture['project_id'], 'f020-large.bin', $large, 80 * 1024 * 1024);
file_put_contents(__DIR__.'/../../.runtime/projecta/api-fixture.json', json_encode($fixture));
echo "PASS synthetic private attachment fixture\n";
