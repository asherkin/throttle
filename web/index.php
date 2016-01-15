<?php

$app = require_once __DIR__ . '/../app/bootstrap.php';

// Start working on this as soon as possible.
$changesetFuture = new ExecFuture('hg id -i');
$changesetFuture->setCWD(__DIR__ . '/..')->setTimeout(5)->start();

$app->register(new Silex\Provider\ServiceControllerServiceProvider());

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
    'twig.options' => array(
        'cache' => __DIR__ . '/../cache'
    ),
));

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addFilter('reldate', new \Twig_Filter_Function(function($secs) {
        $r = "";

        if ($secs >= 86400) {
            $days = floor($secs / 86400);
            $secs = $secs % 86400;
            $r .= $days . ' day';
            if ($days != 1) {
                $r .= 's';
            }
            if ($secs > 0) {
                $r .= ', ';
            }
        }

        if ($secs >= 3600) {
            $hours = floor($secs / 3600);
            $secs = $secs % 3600;
            $r .= $hours . ' hour';
            if ($hours != 1) {
                $r .= 's';
            }
            if ($secs > 0) {
                $r .= ', ';
            }
        }

        if ($secs >= 60) {
            $minutes = floor($secs / 60);
            $secs = $secs % 60;
            $r .= $minutes . ' minute';
            if ($minutes != 1) {
                $r .= 's';
            }
            if ($secs > 0) {
                $r .= ', ';
            }
        }

        $r .= $secs . ' second';
        if ($secs != 1) {
            $r .= 's';
        }

        return $r;
    }));

    $twig->addFilter('diffdate', new \Twig_Filter_Function(function($ts) {
        $diff = time() - $ts;
        $day_diff = floor($diff / 86400);

        if($day_diff == 0)
        {
            if($diff < 60) return 'just now';
            if($diff < 120) return '1 minute ago';
            if($diff < 3600) return floor($diff / 60) . ' minutes ago';
            if($diff < 7200) return '1 hour ago';
            if($diff < 86400) return floor($diff / 3600) . ' hours ago';
        }

        if($day_diff == 1) return 'yesterday';
        if($day_diff < 7) return $day_diff . ' days ago';
        if($day_diff < 31) return ceil($day_diff / 7) . ' weeks ago';
        if($day_diff < 60) return 'last month';

        return date('F Y', $ts);
    }));

    $twig->addFilter('identicon', new \Twig_Filter_Function(function($string, $size = 20) {
        return 'https://secure.gravatar.com/avatar/' . md5($string) . '?s=' . $size . '&r=any&default=identicon&forcedefault=1';
    }));

    $twig->addFilter('crashid', new \Twig_Filter_Function(function($string) {
        return implode('-', str_split(strtoupper($string), 4));
    }));

    $twig->addFilter('decamel', new \Twig_Filter_Function(function($string) {
        return implode(' ', preg_split('/(?<=[a-z])(?=[A-Z])/x', ucfirst($string)));
    }));

    return $twig;
}));

$app->register(new Silex\Provider\SessionServiceProvider());

if ($app['config'] === false) {
    $app->get('/', function() {
        return 'Missing configuration file, please see app/config.base.php';
    });

    $app->run();
    return;
}

$app['debug'] = $app['debug'] || (($user = $app['session']->get('user')) && $user['admin']);

// Catch PHP errors
Symfony\Component\Debug\ErrorHandler::register();
Symfony\Component\Debug\ExceptionHandler::register($app['debug']);

// Fatal errors don't hit monolog's regular handler.
Symfony\Component\Debug\ErrorHandler::setLogger($app['monolog'], 'deprecation');
Symfony\Component\Debug\ErrorHandler::setLogger($app['monolog'], 'emergency');

if ($app['debug']) {
    $app->register(new Silex\Provider\WebProfilerServiceProvider(), array(
        'profiler.cache_dir' => __DIR__ . '/../cache/profiler',
    ));

    // Install the debug handler (register does this earlier for non-debug env)
    if (!$app['config']['debug'] && isset($app['monolog.handler.debug'])) {
        $app['monolog'] = $app->share($app->extend('monolog', function($monolog, $app) {
            $monolog->pushHandler($app['monolog.handler.debug']);
            return $monolog;
        }));
    }
}

//TODO: Remove crash.steampowered.com when we drop bcompat.
Symfony\Component\HttpFoundation\Request::setTrustedHosts(array('^' . preg_quote($app['config']['hostname']) . '$', '^crash.steampowered.com$'));

$app['openid'] = $app->share(function() use ($app) {
    return new LightOpenID($app['config']['hostname']);
});

if (isset($app['config']['yubicloud-api-key']) && isset($app['config']['yubicloud-client-id'])) {
    $app['yubikey'] = $app->share(function() use ($app) {
        return new Yubikey\Validate($app['config']['yubicloud-api-key'], $app['config']['yubicloud-client-id']);
    });
}

$app['queue'] = $app->share(function() use ($app) {
    return new Pheanstalk\Pheanstalk('127.0.0.1');
});

list($err, $stdout, $stderr) = $changesetFuture->resolve();

if (!$err) {
    $app['version'] = $stdout;
}

if (!$app['debug']) {
    $app->error(function(\Exception $e, $code) use ($app) {
        $icon = 'exclamation-sign';
        $title = 'An Error Has Occurred';
        $comment = 'Someone has been dispatched to poke the server with a sharp stick.';

        switch ($code) {
        case 403:
            $icon = 'ban-circle';
            $title = 'Access Denied';
            $comment = 'Doesn\'t look like you\'re meant to be here.';
            break;
        case 404:
            $icon = 'question-sign';
            $title = 'Not Found';
            $comment = 'What you are looking for is not here,'.PHP_EOL.'Unless you were looking for this error page of course.';
            break;
        }

        return $app['twig']->render('error.html.twig', array(
            'icon' => $icon,
            'title' => $title,
            'comment' => $comment,
        ));
    });
}

$app->get('/login/yubikey', 'Throttle\Home::login_yubikey')
    ->bind('yubikey');

$app->post('/login/yubikey', 'Throttle\Home::login_yubikey_post')
    ->bind('yubikey_post');

$app->get('/login', 'Throttle\Home::login')
    ->bind('login');

$app->get('/logout', 'Throttle\Home::logout')
    ->bind('logout');

$app->post('/symbols/submit', 'Throttle\Symbols::submit')
    ->value('_format', 'txt');

$app->post('/submit', 'Throttle\Crash::submit')
    ->value('_format', 'txt');

$app->get('/dashboard', 'Throttle\Crash::dashboard')
    ->bind('dashboard');

$app->get('/dashboard/all/{offset}', 'Throttle\Crash::list_crashes')
    ->assert('offset', '[0-9]+')
    ->value('offset', null)
    ->bind('list');

$app->get('/stats/daily/{module}/{function}', 'Throttle\Stats::daily')
    ->value('module', null)
    ->value('function', null)
    ->bind('stats_daily');

$app->get('/stats/top/{module}/{function}', 'Throttle\Stats::top')
    ->value('module', null)
    ->value('function', null)
    ->bind('stats_top');

$app->get('/stats/latest/{module}/{function}', 'Throttle\Stats::latest')
    ->value('module', null)
    ->value('function', null)
    ->bind('stats_latest');

$app->get('/stats/{module}/{function}', 'Throttle\Stats::index')
    ->value('module', null)
    ->value('function', null)
    ->bind('stats');

$app->get('/{id}/download', 'Throttle\Crash::download')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('download');

$app->get('/{id}/view', 'Throttle\Crash::view')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('view');

$app->get('/{id}/logs', 'Throttle\Crash::logs')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('logs');

$app->get('/{id}/metadata', 'Throttle\Crash::metadata')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('metadata');

$app->get('/{id}/console', 'Throttle\Crash::console')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('console');

$app->get('/{id}/error', 'Throttle\Crash::error')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('error');

$app->post('/{id}/reprocess', 'Throttle\Crash::reprocess')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('reprocess');

$app->post('/{id}/delete', 'Throttle\Crash::delete')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('delete');

$app->get('/{id}', 'Throttle\Crash::details')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('details');

$app->get('/{uuid}', function($uuid) use ($app) {
    $uuid = substr($uuid, 20, 3) . substr($uuid, 24);
    $uuid = str_split($uuid);
    $bid = '';
    for ($i = 0; $i < 15; $i++) {
        $bid .= sprintf('%04b', hexdec($uuid[$i]));
    }
    $bid = str_split($bid, 5);

    $id = '';
    $map = array_merge(range('a', 'z'), range('2', '7'));
    for ($i = 0; $i < 12; $i++) {
        $id .= $map[bindec($bid[$i])];
    }

    return $app->redirect($app['url_generator']->generate('details', array('id' => $id)));
})->assert('uuid', '[0-9a-fA-F-]{36}');

$app->get('/', 'Throttle\Home::index')
    ->bind('index');

$app->run();

