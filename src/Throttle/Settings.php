<?php

namespace Throttle;

use Silex\Application;

class Settings
{
    public function settings(Application $app)
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        $sharing = $app['db']->executeQuery('SELECT share.user AS id, user.name, user.avatar, accepted FROM share LEFT JOIN user ON share.user = user.id WHERE share.owner = ?', array($app['user']['id']))->fetchAll();
        $shared = $app['db']->executeQuery('SELECT share.owner AS id, user.name, user.avatar, accepted FROM share LEFT JOIN user ON share.owner = user.id WHERE share.user = ?', array($app['user']['id']))->fetchAll();

        return $app['twig']->render('settings.html.twig', [
            'sharing' => $sharing,
            'shared' => $shared,
        ]);
    }

    public function invite(Application $app)
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        return $app['twig']->render('invite.html.twig');
    }

    public function invite_post(Application $app)
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        $user = $app['request']->get('user', null);
        if ($user === null) {
            $app['session']->getFlashBag()->add('error_share_invite', 'Missing Steam ID');
            return $app->redirect($app['url_generator']->generate('share_invite'));
        }

        if (!ctype_digit($user) || gmp_cmp(gmp_and($user, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
            $app['session']->getFlashBag()->add('error_share_invite', 'Invalid Steam ID');
            return $app->redirect($app['url_generator']->generate('share_invite'));
        }

        if ($user === $app['user']['id']) {
            $app['session']->getFlashBag()->add('error_share_invite', 'You already have full access to your own reports');
            return $app->redirect($app['url_generator']->generate('share_invite'));
        }

        $query = $app['db']->executeQuery('SELECT accepted FROM share WHERE owner = ? AND user = ?', array($app['user']['id'], $user))->fetch();
        if ($query !== false) {
            if ($query['accepted']) {
                $app['session']->getFlashBag()->add('error_share_invite', 'You have already granted that user access');
            } else {
                $app['session']->getFlashBag()->add('error_share_invite', 'You have already invited that user, but they have not accepted yet');
            }
            return $app->redirect($app['url_generator']->generate('share_invite'));
        }

        $app['db']->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', array($user));
        $app['db']->executeUpdate('INSERT INTO share (owner, user) VALUES (?, ?)', array($app['user']['id'], $user));

        $return = $app['request']->get('return', null);
        if (!$return) {
            $return = $app['url_generator']->generate('settings');
        }

        return $app->redirect($return);
    }

    public function accept(Application $app)
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        $owner = $app['request']->get('owner', null);
        if ($owner === null || !ctype_digit($owner) || gmp_cmp(gmp_and($owner, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
            throw new \Exception('Missing or invalid target');
        }

        $app['db']->executeUpdate('UPDATE share SET accepted = 1 WHERE owner = ? AND user = ?', array($owner, $app['user']['id']));

        $return = $app['request']->get('return', null);
        if (!$return) {
            $return = $app['url_generator']->generate('settings');
        }

        return $app->redirect($return);
    }

    public function revoke(Application $app)
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        $user = $app['request']->get('user', null);
        $owner = $app['request']->get('owner', null);
        if ($user === $owner || ($user !== null && $owner !== null)) {
            throw new \Exception('Missing or multiple targets');
        }

        if ($user !== null) {
            if (!ctype_digit($user) || gmp_cmp(gmp_and($user, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
                throw new \Exception('Invalid target');
            }

            $app['db']->executeUpdate('DELETE FROM share WHERE owner = ? AND user = ?', array($app['user']['id'], $user));
        } else if ($owner !== null) {
            if (!ctype_digit($owner) || gmp_cmp(gmp_and($owner, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
                throw new \Exception('Invalid target');
            }

            $app['db']->executeUpdate('DELETE FROM share WHERE owner = ? AND user = ?', array($owner, $app['user']['id']));
        }

        $return = $app['request']->get('return', null);
        if (!$return) {
            $return = $app['url_generator']->generate('settings');
        }

        return $app->redirect($return);
    }
}

