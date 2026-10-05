<?php

/*
|--------------------------------------------------------------------------
| Shared-hosting bootstrap (project root == document root)
|--------------------------------------------------------------------------
| Used only when the whole project is uploaded into public_html and
| mod_rewrite is unavailable; normally the root .htaccess sends requests
| straight to public/index.php and this file is never executed.
*/

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';

require __DIR__.'/public/index.php';
