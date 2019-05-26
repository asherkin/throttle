<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class Sharing extends AbstractController
{
    /**
     * @Route("/settings/share", name="share")
     */
    public function share()
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        $sharing = $app['db']->executeQuery('SELECT share.user AS id, user.name, user.avatar, accepted FROM share LEFT JOIN user ON share.user = user.id WHERE share.owner = ? ORDER BY accepted IS NULL DESC, accepted DESC', array($app['user']['id']))->fetchAll();
        $shared = $app['db']->executeQuery('SELECT share.owner AS id, user.name, user.avatar, accepted FROM share LEFT JOIN user ON share.owner = user.id WHERE share.user = ? ORDER BY accepted IS NULL DESC, accepted DESC', array($app['user']['id']))->fetchAll();

        return $this->render('share.html.twig', [
            'sharing' => $sharing,
            'shared' => $shared,
        ]);
    }

    public function invite()
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        return $this->render('invite.html.twig');
    }

    public function invite_post()
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        $user = $app['request']->get('user', null);
        if ($user === null) {
            $this->addFlash('error_share_invite', 'Missing Steam ID');
            return $app->redirect($app['url_generator']->generate('share_invite'));
        }

        if (!ctype_digit($user) || gmp_cmp(gmp_and($user, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
            $this->addFlash('error_share_invite', 'Invalid Steam ID');
            return $app->redirect($app['url_generator']->generate('share_invite'));
        }

        if ($user === $app['user']['id']) {
            $this->addFlash('error_share_invite', 'You already have full access to your own reports');
            return $app->redirect($app['url_generator']->generate('share_invite'));
        }

        $query = $app['db']->executeQuery('SELECT accepted FROM share WHERE owner = ? AND user = ?', array($app['user']['id'], $user))->fetch();
        if ($query !== false) {
            if ($query['accepted'] !== null) {
                $this->addFlash('error_share_invite', 'You have already granted that user access');
            } else {
                $this->addFlash('error_share_invite', 'You have already invited that user, but they have not accepted yet');
            }
            return $app->redirect($app['url_generator']->generate('share_invite'));
        }

        $app['db']->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', array($user));
        $app['db']->executeUpdate('INSERT INTO share (owner, user) VALUES (?, ?)', array($app['user']['id'], $user));

        $return = $app['request']->get('return', null);
        if (!$return) {
            $return = $app['url_generator']->generate('share');
        }

        return $app->redirect($return);
    }

    public function accept()
    {
        if (!$app['user']) {
            $app->abort(401);
        }

        $owner = $app['request']->get('owner', null);
        if ($owner === null || !ctype_digit($owner) || gmp_cmp(gmp_and($owner, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
            throw new \Exception('Missing or invalid target');
        }

        $app['db']->executeUpdate('UPDATE share SET accepted = NOW() WHERE owner = ? AND user = ?', array($owner, $app['user']['id']));

        $return = $app['request']->get('return', null);
        if (!$return) {
            $return = $app['url_generator']->generate('share');
        }

        return $app->redirect($return);
    }

    public function revoke()
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
            $return = $app['url_generator']->generate('share');
        }

        return $app->redirect($return);
    }
}

