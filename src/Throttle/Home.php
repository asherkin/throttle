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
                $app['session']->getFlashBag()->add('error', 'Invalid Crash ID');
                return $app->redirect($app['url_generator']->generate('index'));
            }
        }

        $stats = $app['db']->fetchAssoc('SELECT COALESCE(SUM(processed = 1), 0) as processed, COALESCE(SUM(processed = 0), 0) as pending FROM crash');

        return $app['twig']->render('index.html.twig', array(
            'maintenance_message' => null,
            'errors' => $app['session']->getFlashBag()->get('error'),
            'processed' => $stats['processed'],
            'pending' => $stats['pending'],
        ));
    }

    public function login(Application $app)
    {
        if (!$app['openid']->mode) {
            $app['openid']->identity = 'http://steamcommunity.com/openid';
            return $app->redirect($app['openid']->authUrl());
        } elseif($app['openid']->mode == 'cancel' || !$app['openid']->validate()) {
            $app['session']->getFlashBag()->add('error', 'There was a problem during authentication');
            return $app->redirect($app['url_generator']->generate('index'));
        } else {
            $user = preg_replace('/^http\:\/\/steamcommunity\.com\/openid\/id\//', '', $app['openid']->identity);

            $admin = ($user == 76561197987819599);

            $app['session']->set('user', array(
                'id' => $user,
                'admin' => $admin,
            ));

            return $app->redirect($app['url_generator']->generate('list'));
        }
    }
}

