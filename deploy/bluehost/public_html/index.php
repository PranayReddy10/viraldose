<?php

/*
|--------------------------------------------------------------------------
| ViralDose – public_html bridge for Bluehost / cPanel shared hosting
|--------------------------------------------------------------------------
| Copy this file (and everything else from this folder) into public_html.
| The Laravel application itself lives one level up, outside the web root,
| e.g. /home/USER/viraldose. Adjust APP_DIR if you used another folder.
*/

$appDir = __DIR__.'/../viraldose';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = $appDir.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appDir.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appDir.'/bootstrap/app.php';

// Tell Laravel that THIS folder is the public path (assets, storage symlink, Vite manifest).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
