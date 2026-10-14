<?php

declare(strict_types=1);

// Only temporary runtime files live here; cloud sessions and cache use the database.
$scratch = sys_get_temp_dir().'/anteraja-pharma';
foreach (['bootstrap', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
    $path = $scratch.'/'.$directory;
    if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
        http_response_code(503);
        exit('Runtime storage unavailable.');
    }
}
foreach (['CONFIG' => 'config', 'SERVICES' => 'services', 'PACKAGES' => 'packages', 'ROUTES' => 'routes', 'EVENTS' => 'events'] as $name => $file) {
    putenv('APP_'.$name.'_CACHE='.$scratch.'/bootstrap/'.$file.'.php');
}
putenv('LARAVEL_STORAGE_PATH='.$scratch);
$_ENV['LARAVEL_STORAGE_PATH'] = $scratch;
$_SERVER['LARAVEL_STORAGE_PATH'] = $scratch;

// Preserve the original /api route when the serverless entrypoint is invoked.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
unset($_SERVER['PATH_INFO']);

// Composer may resolve relative PSR-4 paths against the deployment root.
$backend = dirname(__DIR__).'/server';
$loader = require $backend.'/vendor/autoload.php';
$loader->addPsr4('App\\', $backend.'/app', true);
$loader->addPsr4('Database\\Seeders\\', $backend.'/database/seeders', true);

require $backend.'/public/index.php';
