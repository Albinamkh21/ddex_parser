<?php
error_reporting( E_ALL );
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
require '../vendor/autoload.php';
require '../helpers/helper.php';


use Study\EmailValidator\App;


$app = new App();
try
{
    $app->run();
}
catch (\Exception $e) {
    print $e->getMessage();
}






