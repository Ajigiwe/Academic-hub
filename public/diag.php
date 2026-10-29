<?php

/**
 * TEMPORARY deployment diagnostic — delete immediately after use.
 *
 * Usage: https://amgacademichub.com/diag.php?token=diagnose
 */

if (($_GET['token'] ?? '') !== 'diagnose') {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain');

const APP_ROOT = '/home/amgacade/repositories/Academic-hub';

echo 'PHP ' . PHP_VERSION . ' — ' . date('c') . PHP_EOL . PHP_EOL;

$paths = [
    '.env',
    'vendor/autoload.php',
    'bootstrap/app.php',
    'bootstrap/cache',
    'storage/logs',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/framework/cache/data',
];
foreach ($paths as $p) {
    $f = APP_ROOT . '/' . $p;
    echo str_pad($p, 34)
        . (file_exists($f) ? 'exists   ' : 'MISSING  ')
        . (file_exists($f) ? (is_writable($f) ? 'writable' : 'NOT writable') : '')
        . PHP_EOL;
}

echo PHP_EOL . '--- boot attempt ---' . PHP_EOL;
try {
    require APP_ROOT . '/vendor/autoload.php';
    echo 'autoload: OK' . PHP_EOL;

    $app = require APP_ROOT . '/bootstrap/app.php';
    echo 'app.php: OK (' . get_class($app) . ')' . PHP_EOL;

    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo 'bootstrap: OK' . PHP_EOL;

    echo 'APP_ENV=' . $app['config']->get('app.env')
        . ' debug=' . var_export($app['config']->get('app.debug'), true)
        . ' key=' . ($app['config']->get('app.key') ? 'set' : 'MISSING')
        . ' url=' . $app['config']->get('app.url') . PHP_EOL;

    $ok = $app['db']->select('select 1 as ok');
    echo 'DB connection: OK' . PHP_EOL;

    $tables = $app['db']->select('show tables');
    echo 'tables in database: ' . count($tables) . PHP_EOL;
    foreach ($tables as $t) {
        echo '  - ' . array_values((array) $t)[0] . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . get_class($e) . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}
