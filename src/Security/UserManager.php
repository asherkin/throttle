<?php

namespace App\Security;

use App\Entity\ExternalAccount;
use App\Entity\User;
use App\Message\CheckUserRegisteredMessage;
use App\Repository\ExternalAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class UserManager
{
    private EntityManagerInterface $entityManager;
    private ExternalAccountRepository $externalAccountRepository;
    private MessageBusInterface $bus;
    private int $loginLinkLifetime;

    public function __construct(EntityManagerInterface $entityManager, ExternalAccountRepository $externalAccountRepository, MessageBusInterface $bus, int $loginLinkLifetime)
    {
        $this->entityManager = $entityManager;
        $this->externalAccountRepository = $externalAccountRepository;
        $this->bus = $bus;
        $this->loginLinkLifetime = $loginLinkLifetime;
    }

    public function findOrCreateExternalAccount(string $kind, string $identifier, string $displayName): ExternalAccount
    {
        $externalAccount = $this->externalAccountRepository->findOneBy([
            'kind' => $kind,
            'identifier' => $identifier,
        ]);

        if ($externalAccount !== null) {
            $user = $externalAccount->getUser();

            // If the user's existing name matches the existing external account display name, update it.
            if ($user->getName() === $externalAccount->getDisplayName()) {
                $user->setName($displayName);
            }

            $externalAccount->setDisplayName($displayName);

            $this->entityManager->flush();

            return $externalAccount;
        }

        // TODO: Display a warning interstitial that the user might be creating a new account.
        $user = new User();
        $user->setName($displayName);

        $externalAccount = new ExternalAccount();
        $externalAccount->setUser($user);
        $externalAccount->setKind($kind);
        $externalAccount->setIdentifier($identifier);
        $externalAccount->setDisplayName($displayName);

        $this->entityManager->persist($user);
        $this->entityManager->persist($externalAccount);
        $this->entityManager->flush();

        return $externalAccount;
    }

    public function findOrCreateUserForEmailAddress(string $emailAddress): User
    {
        $atPosition = mb_strrpos($emailAddress, '@');
        $displayName = mb_substr($emailAddress, 0, ($atPosition !== false) ? $atPosition : null);
        $externalAccount = $this->findOrCreateExternalAccount('email', $emailAddress, $displayName);

        $user = $externalAccount->getUser();

        // If findOrCreateExternalAccount just created this user for us, schedule a check that they logged in.
        // TODO: It would probably be significantly cleaner to implement our own LoginLink implementation that
        //       did all the work post-login - there is really no reason to do it before they click the link...
        //       As another alternative, we may be able to use a temporary in-memory User for the login link,
        //       and implement a custom user provider that calls us to create the real user when the link is consumed.
        if ($user->getLastLogin() === null) {
            $this->bus->dispatch(new CheckUserRegisteredMessage($user->getId()), [
                DelayStamp::delayFor(new \DateInterval(sprintf('PT%dS', $this->loginLinkLifetime * 3))),
            ]);
        }

        return $user;
    }
}
