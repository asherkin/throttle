<?php

$app = require_once __DIR__ . '/../app/bootstrap.php';

// Start working on this as soon as possible.
$changesetFuture = new ExecFuture('hg id -i');
if ($app['config']['show-version']) {
    $changesetFuture->setCWD(__DIR__ . '/..')->setTimeout(5)->start();
}

$app->register(new Silex\Provider\ServiceControllerServiceProvider());

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
    'twig.options' => array(
        'cache' => __DIR__ . '/../cache'
    ),
));

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addFilter(new \Twig_SimpleFilter('reldate', function($secs) {
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

    $twig->addFilter(new \Twig_SimpleFilter('diffdate', function($ts) {
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

        if($day_diff == 1) return '1 day ago';
        if($day_diff < 7) return $day_diff . ' days ago';
        if($day_diff < 31) return ceil($day_diff / 7) . ' weeks ago';
        if($day_diff < 60) return '1 month ago';

        return date('F Y', $ts);
    }));

    $twig->addFilter(new \Twig_SimpleFilter('identicon', function($string, $size = 20) {
        return 'https://secure.gravatar.com/avatar/' . md5($string) . '?s=' . ($size * 2) . '&r=any&default=identicon&forcedefault=1';
    }));

    $twig->addFilter(new \Twig_SimpleFilter('crashid', function($string) {
        return implode('-', str_split(strtoupper($string), 4));
    }));

    $twig->addFilter(new \Twig_SimpleFilter('format_metadata_key', function($string) {
        $name = implode(' ', array_map(function($d) {
            switch (strtolower($d)) {
            case 'url':
            case 'lsb':
            case 'pid':
            case 'guid':
            case 'id':
            case 'mvp':
                return strtoupper($d);
            default:
                return ucfirst($d);
            }
        }, preg_split('/(?:(?<=[a-z])(?=[A-Z])|_|-)/x', $string)));

        switch ($name) {
        case 'Prod':
            return 'Host Product';
        case 'Ver':
            return 'Host Version';
        case 'Rept':
            return 'Reporter';
        case 'Ptime':
            return 'Process Time';
        case 'Source Mod Path':
            return 'SourceMod Path';
        default:
            return $name;
        }
    }));

    $twig->addFilter(new \Twig_SimpleFilter('address', function($string) {
        return sprintf('0x%08s', $string);
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

$user = $app['session']->get('user');

// Force a logout if the information stored in the user session has changed.
if ($user && (!isset($user['version']) || $user['version'] !== Throttle\Home::SESSION_VERSION)) {
    $app['session']->remove('user');
    $user = null;
}

$developer = $user && in_array($user['id'], $app['config']['developers']);

$app['debug'] = $app['debug'] || $developer;

// Catch PHP errors
Symfony\Component\Debug\ExceptionHandler::register($app['debug']);
Symfony\Component\Debug\ErrorHandler::register();

// Fatal errors don't hit monolog's regular handler.
//Symfony\Component\Debug\ErrorHandler::setLogger($app['monolog'], 'deprecation');
Symfony\Component\Debug\ErrorHandler::setLogger($app['monolog'], 'emergency');

if ($app['debug']) {
    $app->register(new Silex\Provider\WebProfilerServiceProvider(), array(
        'profiler.cache_dir' => __DIR__ . '/../cache/profiler',
    ));

    $app->register(new Sorien\Provider\DoctrineProfilerServiceProvider());

    // Install the debug handler (register does this earlier for non-debug env)
    if (!$app['config']['debug'] && isset($app['monolog.handler.debug'])) {
        $app['monolog'] = $app->share($app->extend('monolog', function($monolog, $app) {
            $monolog->pushHandler($app['monolog.handler.debug']);
            return $monolog;
        }));
    }
}

$app->error(function(\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    if ($code == 401) {
        return $app->redirect($app['url_generator']->generate('login', array('return' => $app['request']->getPathInfo())));
    }

    $icon = 'exclamation-sign';
    $title = 'An Error Has Occurred';
    $comment = 'Someone has been dispatched to poke the server with a sharp stick.';

    if ($code >= 400 && $code < 500) {
        $icon = 'remove-sign';
        $title = 'An Error Has Occurred';
        $comment = 'There was a problem with your request.';
    }

    if ($code >= 500 && $code < 600) {
        $app['monolog']->crit(sprintf('Uncaught Exception %s: "%s" at %s line %s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()), ['code' => $code, 'exception' => $e, 'trace' => $e->getTraceAsString()]);
    }

    // Move this to the start if this works...
    if (get_class($e) === 'Symfony\Component\Debug\Exception\DummyException') {
        return;
    }

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
    case 405:
        //$icon = '';
        $title = 'Method Not Allowed';
        //$comment = '';
    }

    return $app['twig']->render('error.html.twig', array(
        'icon' => $icon,
        'title' => $title,
        'comment' => $comment,
    ));
}, -1);

$app['user'] = null;

if ($user) {
    $startTime = microtime(true);
    $details = $app['db']->executeQuery('SELECT name, avatar, (SELECT COUNT(*) FROM share WHERE user = id AND accepted IS NULL) AS pending FROM user WHERE id = ? LIMIT 1', array($user['id']))->fetch();
    $app['monolog']->info(sprintf('Loaded user details in %fms', (microtime(true) - $startTime) / 1000));

    $app['user'] = array(
        'id' => $user['id'],
        'name' => $details ? $details['name'] : null,
        'avatar' => $details ? $details['avatar'] : null,
        'pending' => $details ? $details['pending'] : 0,
        'admin' => in_array($user['id'], $app['config']['admins']),
    );
}

$app['feature'] = array(
    'subscriptions' => false, //$app['user'] && ($developer || in_array($app['user']['id'], ['...'])),
);

Symfony\Component\HttpFoundation\Request::setTrustedProxies($app['config']['trusted-proxies']);

//TODO: Remove crash.steampowered.com when we drop bcompat.
Symfony\Component\HttpFoundation\Request::setTrustedHosts(array('^' . preg_quote($app['config']['hostname']) . '\\.?$', '^crash.steampowered.com\\.?$'));

$app['openid'] = $app->share(function() use ($app) {
    return new LightOpenID($app['config']['hostname']);
});

if ($app['config']['show-version']) {
    list($err, $stdout, $stderr) = $changesetFuture->resolve();

    if (!$err) {
        $app['version'] = $stdout;
    }
}

$app->get('/login', 'Throttle\Home::login')
    ->bind('login');

$app->get('/logout', 'Throttle\Home::logout')
    ->bind('logout');

$app->post('/symbols/submit', 'Throttle\Symbols::submit')
    ->value('_format', 'txt');

$app->post('/submit', 'Throttle\Crash::submit')
    ->value('_format', 'txt');

$app->get('/submit', function() use ($app) {
    $icon = 'remove-sign';
    $title = 'Method Not Allowed';
    $comment = 'The Acclerator extension must be used to upload crash dumps.';

    return $app['twig']->render('error.html.twig', array(
        'icon' => $icon,
        'title' => $title,
        'comment' => $comment,
    ));
});

$app->get('/dashboard/{offset}', 'Throttle\Crash::dashboard')
    ->assert('offset', '[0-9]+')
    ->value('offset', null)
    ->bind('dashboard');

$app->get('/stats/today', 'Throttle\Stats::today')
    ->bind('stats_today');

$app->get('/stats/lifetime', 'Throttle\Stats::lifetime')
    ->bind('stats_lifetime');

$app->get('/stats/unique', 'Throttle\Stats::unique')
    ->bind('stats_unique');

$app->get('/stats/daily/{module}/{function}', 'Throttle\Stats::daily')
    ->value('module', null)
    ->value('function', null)
    ->bind('stats_daily');

$app->get('/stats/hourly/{module}/{function}', 'Throttle\Stats::hourly')
    ->value('module', null)
    ->value('function', null)
    ->bind('stats_hourly');

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

$app->post('/paddle_webhook', 'Throttle\Subscription::webhook')
    ->bind('paddle_webhook');

$app->get('/subscribe', 'Throttle\Subscription::subscribe')
    ->bind('subscribe');

$app->get('/settings', function() use ($app) {
    return $app->redirect($app['url_generator']->generate('share'));
})->bind('settings');

$app->get('/settings/share', 'Throttle\Sharing::share')
    ->bind('share');

$app->get('/settings/share/invite', 'Throttle\Sharing::invite')
    ->bind('share_invite');

$app->post('/settings/share/invite', 'Throttle\Sharing::invite_post')
    ->bind('share_invite_post');

$app->post('/settings/share/accept', 'Throttle\Sharing::accept')
    ->bind('share_accept');

$app->post('/settings/share/revoke', 'Throttle\Sharing::revoke')
    ->bind('share_revoke');

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

$app->get('/{id}/carburetor', 'Throttle\Crash::carburetor')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('carburetor');

$app->get('/{id}/carburetor/data', 'Throttle\Crash::carburetor_data')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('carburetor_data');

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
})->assert('uuid', '[0-9a-fA-F-]{36}')
  ->bind('details_uuid');

$app->get('/', 'Throttle\Home::index')
    ->bind('index');

$app->run();

