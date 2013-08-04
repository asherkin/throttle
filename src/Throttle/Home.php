<?php

namespace Throttle;

use Silex\Application;

class Home
{
    public function index(Application $app)
    {
        $id = $app['request']->query->get('id');
        if (isset($id)) {
            $id = strtolower(str_replace('-', '', $id));

            try {
                $app['session']->getFlashBag()->set('internal', 'true');

                return $app->redirect($app['url_generator']->generate('details', array('id' => $id)));
            } catch (\Exception $e) {
                $app['session']->getFlashBag()->add('error_crash', 'Invalid Crash ID');

                return $app->redirect($app['url_generator']->generate('index'));
            }
        }

        $stats = $app['db']->executeQuery('SELECT COALESCE(SUM(processed = 1 AND failed = 0), 0) as processed, COALESCE(SUM(processed = 0), 0) as pending, COALESCE(SUM(failed = 1), 0) as failed FROM crash')->fetch();

        return $app['twig']->render('index.html.twig', array(
            'maintenance_message' => $app['config']['maintenance'],
            'errors_crash' => $app['session']->getFlashBag()->get('error_crash'),
            'errors_auth' => $app['session']->getFlashBag()->get('error_auth'),
            'stats' => $stats,
        ));
    }

    public function login(Application $app)
    {
        if (!$app['openid']->mode) {
            $app['openid']->identity = 'http://steamcommunity.com/openid';

            return $app->redirect($app['openid']->authUrl());
        } elseif ($app['openid']->mode == 'cancel' || !$app['openid']->validate()) {
            $app['session']->getFlashBag()->add('error_auth', 'There was a problem during authentication');

            return $app->redirect($app['url_generator']->generate('index'));
        } else {
            $id = preg_replace('/^http\:\/\/steamcommunity\.com\/openid\/id\//', '', $app['openid']->identity);

            $user = $app['db']->executeQuery('SELECT name, avatar FROM user WHERE id = ? LIMIT 1', array($id))->fetch();

            if (!$user) {
                $app['db']->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', array($id));
                $user = array('name' => null, 'avatar' => null);
            }

            $app['session']->set('user', array(
                'id' => $id,
                'name' => $user['name'],
                'avatar' => $user['avatar'],
                'admin' => in_array($id, $app['config']['admins']),
            ));

            $return = $app['request']->get('return', null);
            if ($return) {
                return $app->redirect($return);
            }

            return $app->redirect($app['url_generator']->generate('list'));
        }
    }

    public function logout(Application $app)
    {
        $app['session']->remove('user');

        return $app->redirect($app['url_generator']->generate('index'));
    }
}

