<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class Home extends AbstractController
{
    const SESSION_VERSION = 2;

    /**
     * @Route("/", name="index")
     */
    public function index(Request $request)
    {
        $id = $request->query->get('id');
        if (isset($id)) {
            $crashid = strtolower(str_replace('-', '', $id));

            try {
                $app['session']->getFlashBag()->set('internal', 'true');

                return $this->redirectToRoute('details', array('id' => $crashid));
            } catch (\Exception $e) {
                try {
                    return $this->redirectToRoute('details_uuid', array('uuid' => $id));
                } catch (\Exception $e) {
                    $app['session']->getFlashBag()->add('error_crash', 'Invalid Crash ID.');

                    return $this->redirectToRoute('index');
                }
            }
        }

        return $this->render('index.html.twig', array(
            'maintenance_message' => '', //$app['config']['maintenance'],
        ));
    }

    /**
     * @Route("/login", name="login")
     */
    public function login()
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

        $id = preg_replace('/^https?\:\/\/steamcommunity\.com\/openid\/id\//', '', $app['openid']->identity);

        $app['db']->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', array($id));

        $app['session']->set('user', array(
            'version' => self::SESSION_VERSION,
            'id' => $id,
        ));

        $returnUrl = $app['request']->get('return', $app['url_generator']->generate('dashboard'));
        return $app->redirect($returnUrl);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        $app['session']->remove('user');

        $returnUrl = $app['request']->get('return', $app['url_generator']->generate('index'));
        return $app->redirect($returnUrl);
    }
}

