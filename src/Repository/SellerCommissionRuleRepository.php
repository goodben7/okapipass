<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\SellerCommissionRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SellerCommissionRule> */
class SellerCommissionRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SellerCommissionRule::class);
    }

    /** @return list<SellerCommissionRule> */
    public function findActiveForAgency(Agency $agency): array
    {
        /** @var list<SellerCommissionRule> $rows */
        $rows = $this->createQueryBuilder('r')
            ->andWhere('r.agency = :agency')
            ->andWhere('r.active = true')
            ->setParameter('agency', $agency)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
