<?php

namespace App\Security\Voter;

use App\Entity\Server;
use App\Entity\User;

/**
 * @extends EntityActionVoter<Server>
 */
class ServerVoter extends EntityActionVoter
{
    public const EDIT = 'SERVER_EDIT';
    public const VIEW = 'SERVER_VIEW';

    protected function supportedEntityType(): string
    {
        return Server::class;
    }

    protected function supportedActions(): array
    {
        return [self::EDIT, self::VIEW];
    }

    protected function canUserPerformAction(User $user, string $action, object $subject): bool
    {
        return match ($action) {
            self::EDIT, self::VIEW => $user->getServerOwners()->contains($subject->getOwner()),
            default => false,
        };
    }
}
