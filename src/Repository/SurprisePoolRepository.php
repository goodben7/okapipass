<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\SurprisePool;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurprisePool>
 */
class SurprisePoolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurprisePool::class);
    }

    /**
     * @return list<SurprisePool>
     */
    public function findActiveForAgency(Agency $agency): array
    {
        /** @var list<SurprisePool> $pools */
        $pools = $this->createQueryBuilder('p')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.active = true')
            ->setParameter('agency', $agency)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $pools;
    }
}
