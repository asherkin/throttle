<?php

namespace App\Security;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private $db;
    private $appConfig;

    public function __construct(Connection $db, $appConfig)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
    }

    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me.
     *
     * If you're not using these features, you do not need to implement
     * this method.
     *
     * @return UserInterface
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($id)
    {
        $details = $this->db->executeQuery('SELECT name, avatar, UNIX_TIMESTAMP(lastactive) AS lastactive, (SELECT COUNT(*) FROM share WHERE user = id AND accepted IS NULL) AS pending FROM user WHERE id = ? LIMIT 1', [$id])->fetch();

        if (!$details) {
            $this->db->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', [$id]);
        }

        if ($details && ($details['lastactive'] === null || (time() - $details['lastactive']) > (60 * 60 * 24))) {
            $this->db->executeUpdate('UPDATE user SET lastactive = NOW() WHERE id = ?', [$id]);
        }

        $user = (new SteamUser($id))
            ->setName($details ? $details['name'] : null)
            ->setAvatar($details ? $details['avatar'] : null)
            ->setPending($details ? $details['pending'] : 0)
            ->setIsAdmin(in_array($id, $this->appConfig['admins'], true));

        return $user;
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     *
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof SteamUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass($class)
    {
        return $class === SteamUser::class;
    }
}