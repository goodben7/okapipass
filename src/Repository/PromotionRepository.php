<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\Promotion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Promotion>
 */
class PromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promotion::class);
    }

    public function findActiveByCode(Agency $agency, string $code): ?Promotion
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.code = :code')
            ->andWhere('p.active = true')
            ->setParameter('agency', $agency)
            ->setParameter('code', strtoupper(trim($code)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
