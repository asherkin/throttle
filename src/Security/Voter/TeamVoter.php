<?php

namespace App\Security\Voter;

use App\Entity\Team;
use App\Entity\User;

/**
 * @extends EntityActionVoter<Team>
 */
class TeamVoter extends EntityActionVoter
{
    public const EDIT = 'TEAM_EDIT';
    public const VIEW = 'TEAM_VIEW';

    protected function supportedEntityType(): string
    {
        return Team::class;
    }

    protected function supportedActions(): array
    {
        return [self::EDIT, self::VIEW];
    }

    protected function canUserPerformAction(User $user, string $action, object $subject): bool
    {
        return match ($action) {
            self::EDIT, self::VIEW => $subject->getOwner() === $user,
            default => false,
        };
    }
}
