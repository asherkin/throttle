<?php

namespace App\Controller;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class Sharing extends AbstractController
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * @Route("/settings", name="settings")
     */
    public function settings()
    {
        // Placeholder until we need more settings pages.
        return $this->redirectToRoute('share');
    }

    /**
     * @Route("/settings/share", name="share")
     */
    public function share()
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();

        $sharing = $this->db->executeQuery('SELECT share.user AS id, user.name, user.avatar, accepted FROM share LEFT JOIN user ON share.user = user.id WHERE share.owner = ? ORDER BY accepted IS NULL DESC, accepted DESC', [$user->getId()])->fetchAll();
        $shared = $this->db->executeQuery('SELECT share.owner AS id, user.name, user.avatar, accepted FROM share LEFT JOIN user ON share.owner = user.id WHERE share.user = ? ORDER BY accepted IS NULL DESC, accepted DESC', [$user->getId()])->fetchAll();

        return $this->render('share.html.twig', [
            'sharing' => $sharing,
            'shared' => $shared,
        ]);
    }

    /**
     * @Route("/settings/share/invite", methods={"POST"}, name="share_invite_post")
     */
    public function invite_post(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $request->get('user', null);
        if ($user === null) {
            $this->addFlash('error_share_invite', 'Missing Steam ID');

            return $this->redirectToRoute('share_invite');
        }

        if (!ctype_digit($user) || gmp_cmp(gmp_and($user, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
            $this->addFlash('error_share_invite', 'Invalid Steam ID');

            return $this->redirectToRoute('share_invite');
        }

        $currentUser = $this->getUser();
        if ($user === $currentUser->getId()) {
            $this->addFlash('error_share_invite', 'You already have full access to your own reports');

            return $this->redirectToRoute('share_invite');
        }

        $query = $this->db->executeQuery('SELECT accepted FROM share WHERE owner = ? AND user = ?', [$currentUser->getId(), $user])->fetch();
        if ($query !== false) {
            if ($query['accepted'] !== null) {
                $this->addFlash('error_share_invite', 'You have already granted that user access');
            } else {
                $this->addFlash('error_share_invite', 'You have already invited that user, but they have not accepted yet');
            }

            return $this->redirectToRoute('share_invite');
        }

        $this->db->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', [$user]);
        $this->db->executeUpdate('INSERT INTO share (owner, user) VALUES (?, ?)', [$currentUser->getId(), $user]);

        $return = $request->get('return');
        if ($return) {
            return $this->redirect($return);
        }

        return $this->redirectToRoute('share');
    }

    /**
     * @Route("/settings/share/invite", name="share_invite")
     */
    public function invite()
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('invite.html.twig');
    }

    /**
     * @Route("/settings/share/accept", methods={"POST"}, name="share_accept")
     */
    public function accept(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $owner = $request->get('owner', null);
        if ($owner === null || !ctype_digit($owner) || gmp_cmp(gmp_and($owner, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
            throw new \Exception('Missing or invalid target');
        }

        $this->db->executeUpdate('UPDATE share SET accepted = NOW() WHERE owner = ? AND user = ?', [$owner, $this->getUser()->getId()]);

        $return = $request->get('return');
        if ($return) {
            return $this->redirect($return);
        }

        return $this->redirectToRoute('share');
    }

    /**
     * @Route("/settings/share/revoke", methods={"POST"}, name="share_revoke")
     */
    public function revoke(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $request->get('user', null);
        $owner = $request->get('owner', null);
        if ($user === $owner || ($user !== null && $owner !== null)) {
            throw new \Exception('Missing or multiple targets');
        }

        if ($user !== null) {
            if (!ctype_digit($user) || gmp_cmp(gmp_and($user, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
                throw new \Exception('Invalid target');
            }

            $this->db->executeUpdate('DELETE FROM share WHERE owner = ? AND user = ?', [$this->getUser()->getId(), $user]);
        } elseif ($owner !== null) {
            if (!ctype_digit($owner) || gmp_cmp(gmp_and($owner, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
                throw new \Exception('Invalid target');
            }

            $this->db->executeUpdate('DELETE FROM share WHERE owner = ? AND user = ?', [$owner, $this->getUser()->getId()]);
        }

        $return = $request->get('return');
        if ($return) {
            return $this->redirect($return);
        }

        return $this->redirectToRoute('share');
    }
}
