#!/usr/bin/env php
<?php

$app = require_once __DIR__ . '/bootstrap.php';

$app->register(new Cilex\Provider\Console\Adapter\Silex\ConsoleServiceProvider(), array(
    'console.name' => 'Throttle',
    'console.version' => '0.0.0',
));

$app['console']->add(new Throttle\ProcessCommand);

$app['console']->getHelperSet()->set(new \Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($app['db']), 'db');

$app['console']->addCommands(array(
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\ExecuteCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\GenerateCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\MigrateCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\StatusCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\VersionCommand(),
));

$app['console']->run();

