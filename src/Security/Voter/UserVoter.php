<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Security;

/**
 * @extends EntityActionVoter<User>
 */
class UserVoter extends EntityActionVoter
{
    public const DELETE = 'USER_DELETE';
    public const EDIT = 'USER_EDIT';
    public const VIEW = 'USER_VIEW';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supportedEntityType(): string
    {
        return User::class;
    }

    protected function supportedActions(): array
    {
        return [self::DELETE, self::EDIT, self::VIEW];
    }

    protected function canUserPerformAction(User $user, string $action, object $subject): bool
    {
        $isAdmin = $this->security->isGranted(User::ROLE_ADMIN);
        $isCurrentUser = $subject === $user;

        return match ($action) {
            self::DELETE => $isAdmin && !$isCurrentUser,
            self::EDIT, self::VIEW => $isAdmin || $isCurrentUser,
            default => false,
        };
    }
}
