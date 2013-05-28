<?php

$app = require_once __DIR__ . '/../app/bootstrap.php';

$app->register(new Silex\Provider\ServiceControllerServiceProvider());

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
    'twig.options' => array(
        'cache' => __DIR__ . '/../cache'
    ),
));

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addFilter('stackframe', new \Twig_Filter_Function(function($frame) {
        if ($frame['file'] != "") {
            $file = basename(str_replace('\\', '/', $frame['file']));
            $symbol = "${frame['module']}!${frame['function']} [${file}:${frame['line']} + ${frame['offset']}]";
        } else if ($frame['function'] != "") {
            $symbol = "${frame['module']}!${frame['function']} + ${frame['offset']}";
        } else if ($frame['module'] != "") {
            $symbol = "${frame['module']} + ${frame['offset']}";
        } else {
            $symbol = "${frame['offset']}";
        }

        return $symbol;
    }));

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

    $twig->addFilter('identicon', new \Twig_Filter_Function(function($string) {
        return 'https://secure.gravatar.com/avatar/' . md5($string) . '?s=20&r=any&default=identicon&forcedefault=1';
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
        return 'Missing configuration file, please see app/config.dist.php';
    });

    $app->run();
    return;
}

if ($app['config']['debug'] || (($user = $app['session']->get('user')) && $user['admin'])) {
    $app->register(new Silex\Provider\WebProfilerServiceProvider(), array(
        'profiler.cache_dir' => __DIR__ . '/../cache/profiler',
    ));

    // Install the debug handler (register does this for non-debug env)
    if (!$app['debug'] && isset($app['monolog.handler.debug'])) {
        $app['monolog'] = $app->share($app->extend('monolog', function($monolog, $app) {
            $monolog->pushHandler($app['monolog.handler.debug']);
            return $monolog;
        }));
    }
}

$app['openid'] = $app->share(function() use ($app) {
    return new LightOpenID($app['config']['hostname']);
});

$app->get('/login', 'Throttle\Home::login')
    ->bind('login');

$app->post('/symbols/submit', 'Throttle\Symbols::submit')
    ->value('_format', 'txt');

$app->post('/submit', 'Throttle\Crash::submit')
    ->value('_format', 'txt');

$app->get('/list', 'Throttle\Crash::list_crashes')
    ->bind('list');

$app->get('/{id}/stack', 'Throttle\Crash::stack')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('stack');

$app->post('/{id}/reprocess', 'Throttle\Crash::reprocess')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('reprocess');

$app->post('/{id}/delete', 'Throttle\Crash::delete')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('delete');

$app->get('/{id}', 'Throttle\Crash::details')
    ->assert('id', '[0-9a-zA-Z]{12}')
    ->bind('details');

$app->get('/', 'Throttle\Home::index')
    ->bind('index');

$app->run();

