<?php
error_reporting(E_ALL);
                ini_set('display_errors', 1);

                echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
                echo "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none') . "\n";
                echo "Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'none') . "\n";
                echo "Raw body length: " . strlen(file_get_contents('php://input')) . "\n";  // Should be >0

                // Try parsing
                try {
                    [$fields, $files] = request_parse_body();
                    var_dump($fields, $files);
                } catch (RequestParseBodyException $e) {
                    echo "Parsing error: " . $e->getMessage();
                }
                

                die;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
