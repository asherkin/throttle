<?php

require_once __DIR__.'/../vendor/autoload.php';

// libphutil provides static functions that can't be autoloaded by Composer
require_once __DIR__.'/../vendor/facebook/libphutil/src/__phutil_library_init__.php';

// If libphutil is the last autoloader (Composer prepends, so will never be after it), it throws on missing classes...
spl_autoload_register(function ($class) {});

// Catch PHP errors
Symfony\Component\HttpKernel\Debug\ErrorHandler::register();

$app = new Silex\Application();

$app['debug'] = false;
$app['root'] = __DIR__ . '/..';

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_mysql',
        'host'     => 'localhost',
        'user'     => 'throttle',
        'password' => 'throttle',
        'dbname'   => 'throttle',
    ),
));

return $app;

