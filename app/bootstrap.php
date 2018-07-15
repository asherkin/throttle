<?php

error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

// libphutil provides static functions that can't be autoloaded by Composer
require_once __DIR__ . '/../vendor/facebook/libphutil/src/__phutil_library_init__.php';

// If libphutil is the last autoloader (Composer prepends, so will never be after it), it throws on missing classes...
spl_autoload_register(function ($class) {});

$app = new Silex\Application();

$app['root'] = \Filesystem::resolvePath(__DIR__ . '/..');

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => $app['root'] . '/logs/main.log',
    'monolog.handler' => new Monolog\Handler\StreamHandler($app['root'] . '/logs/main.log', Monolog\Logger::NOTICE),
    'monolog.level'   => Monolog\Logger::DEBUG,
    'monolog.name'    => 'throttle',
));

try {
    $app['config'] = include_once __DIR__ . '/config.php';
} catch (ErrorException $e) {
    $app['config'] = false;
    return $app;
}

$app['debug'] = $app['config']['debug'];

$app['monolog'] = $app->share($app->extend('monolog', function($monolog, $app) {
    if ($app['config']['email-errors']) {
        $monolog->pushHandler(new Monolog\Handler\NativeMailerHandler(
            $app['config']['email-errors.to'],
            '[Throttle] Error Report',
            $app['config']['email-errors.from'],
            Monolog\Logger::CRITICAL
        ));
    }

    return $monolog;
}));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'        => 'pdo_mysql',
        'host'          => $app['config']['db.host'],
        'user'          => $app['config']['db.user'],
        'password'      => $app['config']['db.password'],
        'dbname'        => $app['config']['db.name'],
        'driverOptions' => array(
            1002 => 'SET NAMES utf8',
        ),
    ),
));

return $app;

