<?php

require_once __DIR__ . '/../vendor/autoload.php';

// libphutil provides static functions that can't be autoloaded by Composer
require_once __DIR__ . '/../vendor/facebook/libphutil/src/__phutil_library_init__.php';

// If libphutil is the last autoloader (Composer prepends, so will never be after it), it throws on missing classes...
spl_autoload_register(function ($class) {});

// Catch PHP errors
Symfony\Component\HttpKernel\Debug\ErrorHandler::register();

$app = new Silex\Application();

$app['debug'] = false;
$app['root'] = __DIR__ . '/..';

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../logs/main.log',
    'monolog.level'   => Monolog\Logger::WARNING,
    'monolog.name'    => 'throttle',
));

$app['monolog'] = $app->share($app->extend('monolog', function($monolog, $app) {
    if (!$app['debug']) {
        $monolog->pushHandler(new Monolog\Handler\NativeMailerHandler(
            array('asherkin@limetech.org'),
            '[Throttle] Error Report',
            'throttle@limetech.org',
            Monolog\Logger::CRITICAL
        ));
    }

    return $monolog;
}));

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

