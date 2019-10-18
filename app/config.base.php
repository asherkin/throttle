<?php

$base = null;
if (basename(__FILE__) != 'config.base.php') {
    $base = include_once __DIR__ . '/config.base.php';
}

if (!is_array($base)) {
    $base = array();
}

return array_merge($base, array(
    'debug' => false,
    'maintenance' => false,
    'show-version' => true,

    'email-errors' => false,
    'email-errors.from' => 'noreply@example.com',
    'email-errors.to' => array(
        'webmaster@example.com',
    ),

    'db.host' => 'localhost',
    'db.user' => 'root',
    'db.password' => '',
    'db.name' => 'throttle',

    'hostname' => 'throttle.example.com',
    'trusted-proxies' => array(),

    'admins' => array(),
    'developers' => array(),

    'apikey' => false,
    'accelerator' => false,

    'show-version' => true,

    'symbol-stores' => array(),
));

