<?php

use Kanboard\Core\Controller\Runner;

require_once __DIR__.'/app/Core/Http/ErrorHandler.php';
\Kanboard\Core\Http\ErrorHandler::register('web');

try {
    require __DIR__.'/app/common.php';
    $container['router']->dispatch();
    $runner = new Runner($container);
    $runner->execute();
} catch (\Throwable $e) {
    \Kanboard\Core\Http\ErrorHandler::handle($e);
}
