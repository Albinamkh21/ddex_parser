<?php
error_reporting( E_ALL );
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php_error.log');
require 'vendor/autoload.php';
require 'helpers/helper.php';

use Study\EmailValidator\App;
use Study\EmailValidator\Service\Logger;

Logger::registerGlobalHandlers();

$app = new App();
try
{
    $app->run();
}
catch (\Throwable $e) {
    Logger::logException($e, 'App::run');
    print $e->getMessage();
}
