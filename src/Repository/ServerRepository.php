<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Server>
 */
class ServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Server::class);
    }

    /**
     * @return Collection<int, Server>
     */
    public function findAllForUser(User $user): Collection
    {
        $ownerIds = $user->getServerOwners()
            ->map(fn ($owner) => $owner->getId());

        /** @var array<int, Server> $servers */
        $servers = $this->createQueryBuilder('s')
            ->where('s.owner IN (:ids)')
            ->setParameter('ids', $ownerIds)
            ->getQuery()
            ->execute();

        // Implementing this sorting on the DB side requires FIELD()
        // ORDER BY FIELD(s.owner, :ids), s.name
        $ownerSortKey = array_flip($ownerIds->getValues());
        usort($servers, function (Server $a, Server $b) use ($ownerSortKey) {
            $sort = $ownerSortKey[$a->getOwner()->getId()] - $ownerSortKey[$b->getOwner()->getId()];
            if ($sort === 0) {
                $sort = strcmp($a->getName(), $b->getName());
            }

            return $sort;
        });

        return new ArrayCollection($servers);
    }

    /**
     * @return Collection<int, Collection<int, Server>>
     */
    public function findAllForUserGroupedByOwner(User $user): Collection
    {
        /** @var Collection<int, Collection<int, Server>> $groupedServers */
        $groupedServers = new ArrayCollection();

        foreach ($this->findAllForUser($user) as $server) {
            $ownerId = $server->getOwner()->getId();

            $ownerCollection = $groupedServers[$ownerId];
            if ($ownerCollection === null) {
                $ownerCollection = new ArrayCollection();
                $groupedServers[$ownerId] = $ownerCollection;
            }

            $ownerCollection->add($server);
        }

        return $groupedServers;
    }
}
