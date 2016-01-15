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

        return $app['twig']->render('index.html.twig', array(
            'maintenance_message' => $app['config']['maintenance'],
        ));
    }

    public function login(Application $app)
    {
        $errorReturnUrl = $app['request']->get('return', $app['url_generator']->generate('index'));

        if (!$app['openid']->mode) {
            $app['openid']->identity = 'https://steamcommunity.com/openid';

            try {
                return $app->redirect($app['openid']->authUrl());
            } catch (\ErrorException $e) {
                $app['session']->getFlashBag()->add('error_auth', 'Unfortunately Steam Community seems to be having trouble staying online.');
                return $app->redirect($errorReturnUrl);
            }
        }

        if ($app['openid']->mode == 'cancel') {
            $app['session']->getFlashBag()->add('error_auth', 'Authentication was cancelled.');
            return $app->redirect($errorReturnUrl);
        }

        try {
            if (!$app['openid']->validate()) {
                $app['session']->getFlashBag()->add('error_auth', 'There was a problem during authentication.');
                return $app->redirect($errorReturnUrl);
            }
        } catch (\ErrorException $e) {
            $app['session']->getFlashBag()->add('error_auth', 'Unfortunately Steam Community seems to be having trouble staying online.');
            return $app->redirect($errorReturnUrl);
        }

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

        $returnUrl = $app['request']->get('return', $app['url_generator']->generate('dashboard'));
        return $app->redirect($returnUrl);
    }

    public function login_yubikey(Application $app)
    {
        if (!isset($app['yubikey'])) {
            return $app->abort(404);
        }

        return $app['twig']->render('yubikey.html.twig', array(
            'return' => $app['request']->get('return', null),
            'errors' => $app['session']->getFlashBag()->get('error'),
        ));
    }

    public function login_yubikey_post(Application $app)
    {
        if (!isset($app['yubikey'])) {
            return $app->abort(404);
        }

        $otp = $app['request']->get('otp', null);
        if ($otp === null) {
            $app['session']->getFlashBag()->add('error', 'Missing OTP in request');

            return $app->redirect($app['url_generator']->generate('yubikey', array(
                'return' => $app['request']->get('return', null),
            )));
        }

        //$app['session']->getFlashBag()->add('error', 'Failed to authenticate');

        $response = false;
        try {
            $response = $app['yubikey']->check($otp);
        } catch (\InvalidArgumentException $e) {
            $app['session']->getFlashBag()->add('error', $e->getMessage());

            return $app->redirect($app['url_generator']->generate('yubikey', array(
                'return' => $app['request']->get('return', null),
            )));
        }

        if ($response->success() !== true) {
            $app['session']->getFlashBag()->add('error', 'YubiCloud rejected OTP');

            return $app->redirect($app['url_generator']->generate('yubikey', array(
                'return' => $app['request']->get('return', null),
            )));
        }

        $user = substr($otp, 0, 12);
        $userlen = strlen($user);
        $userid = '';
        $modhex = 'cbdefghijklnrtuv';
        for ($i = 0; $i < $userlen; $i += 2) {
            $high = strpos($modhex, $user[$i]);
            $low = strpos($modhex, $user[$i + 1]);
            $userid .= chr(($high << 4) | $low);
        }
        $userid = implode(':', str_split(bin2hex($userid), 2));

        $app['session']->getFlashBag()->add('error', 'Authenticated as ' . $userid);

        return $app->redirect($app['url_generator']->generate('yubikey', array(
            'return' => $app['request']->get('return', null),
        )));
    }

    public function logout(Application $app)
    {
        $app['session']->remove('user');

        return $app->redirect($app['url_generator']->generate('index'));
    }
}

