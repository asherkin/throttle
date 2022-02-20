<?php

namespace App\Repository;

use App\Entity\User;
use App\Message\CheckUserRegisteredMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    private MessageBusInterface $bus;
    private int $loginLinkLifetime;

    public function __construct(ManagerRegistry $registry, MessageBusInterface $bus, ContainerBagInterface $params)
    {
        parent::__construct($registry, User::class);

        $this->bus = $bus;

        /** @var int $loginLinkLifetime */
        $loginLinkLifetime = $params->get('app.login_link_lifetime');
        $this->loginLinkLifetime = $loginLinkLifetime;
    }

    public function findOrCreateOneByUserIdentifier(string $value): User
    {
        $user = $this->findOneBy(['email' => $value]);

        // TODO: Should this logic be in a service?
        //       Particularly so we're not wiring the message bus every time.
        //       And we only really want to do this when creating users from a login link.
        if ($user === null) {
            $user = new User();
            $user->setEmail($value);

            $entityManager = $this->getEntityManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $id = $user->getId();
            if ($id === null) {
                throw new \LogicException('user not persisted yet');
            }

            $this->bus->dispatch(new CheckUserRegisteredMessage($id), [
                DelayStamp::delayFor(new \DateInterval(sprintf('PT%dS', $this->loginLinkLifetime * 3))),
            ]);
        }

        return $user;
    }
}
