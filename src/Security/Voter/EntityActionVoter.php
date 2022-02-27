<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @template T of object
 */
abstract class EntityActionVoter implements VoterInterface, CacheableVoterInterface
{
    /**
     * @return class-string<T>
     */
    abstract protected function supportedEntityType(): string;

    /**
     * @return array<int, string>
     */
    abstract protected function supportedActions(): array;

    /**
     * @param T $subject
     */
    abstract protected function canUserPerformAction(User $user, string $action, object $subject): bool;

    /**
     * @param mixed   $subject
     * @param mixed[] $attributes
     */
    final public function vote(TokenInterface $token, $subject, array $attributes): int
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return self::ACCESS_ABSTAIN;
        }

        if (!\is_object($subject) || !is_a($subject, $this->supportedEntityType())) {
            return self::ACCESS_ABSTAIN;
        }

        /** @var array<int, string> $supportedAttributes */
        $supportedAttributes = array_intersect($attributes, $this->supportedActions());
        if (\count($supportedAttributes) === 0) {
            return self::ACCESS_ABSTAIN;
        }

        // TODO: Need to understand when this can be called with multiple attributes.
        //       It might make more sense for our case to require all instead of any.
        foreach ($supportedAttributes as $attribute) {
            if ($this->canUserPerformAction($user, $attribute, $subject)) {
                return self::ACCESS_GRANTED;
            }
        }

        return self::ACCESS_DENIED;
    }

    final public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, $this->supportedActions(), true);
    }

    final public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, $this->supportedEntityType(), true);
    }
}
