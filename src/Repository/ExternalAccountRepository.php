<?php

namespace App\Repository;

use App\Entity\ExternalAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ExternalAccount|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExternalAccount|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExternalAccount[]    findAll()
 * @method ExternalAccount[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @extends ServiceEntityRepository<ExternalAccount>
 */
class ExternalAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalAccount::class);
    }
}
