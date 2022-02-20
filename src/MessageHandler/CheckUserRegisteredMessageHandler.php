<?php

namespace App\MessageHandler;

use App\Message\CheckUserRegisteredMessage;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class CheckUserRegisteredMessageHandler implements MessageHandlerInterface
{
    private ObjectManager $entityManager;
    private UserRepository $userRepository;

    public function __construct(ManagerRegistry $doctrine, UserRepository $userRepository)
    {
        $this->entityManager = $doctrine->getManager();
        $this->userRepository = $userRepository;
    }

    public function __invoke(CheckUserRegisteredMessage $message): void
    {
        $user = $this->userRepository->find($message->getUserId());

        // If the user doesn't exist anymore, ignore them.
        if ($user === null) {
            return;
        }

        // If the user has a last login set now, ignore them.
        if ($user->getLastLogin() !== null) {
            return;
        }

        // Finally, delete them.
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
