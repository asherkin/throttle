#!/usr/bin/env php
<?php

$app = require_once __DIR__.'/bootstrap.php';

$app->register(new Cilex\Provider\Console\Adapter\Silex\ConsoleServiceProvider(), array(
    'console.name' => 'Accelerator',
    'console.version' => '0.0.0',
));

$app['console']->add(new Accelerator\ProcessCommand);

$app['console']->run();

