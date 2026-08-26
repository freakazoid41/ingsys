<?php
header_remove('X-Powered-By');
header_remove('Server');

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));



$GLOBALS['SYS_CODE'] = 'CATES';
if (strpos($_SERVER['HTTP_HOST'], 'yatagantermik') !== false) {
    $GLOBALS['SYS_CODE'] = 'YATAGAN';
    
}

$GLOBALS['CSP_ADDITIONAL_HOSTS'] = $_SERVER['HTTP_HOST'];


// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
