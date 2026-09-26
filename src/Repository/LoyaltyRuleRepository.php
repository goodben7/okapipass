<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\LoyaltyRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoyaltyRule>
 */
class LoyaltyRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoyaltyRule::class);
    }

    /**
     * @return list<LoyaltyRule>
     */
    public function findActiveForAgency(Agency $agency): array
    {
        /** @var list<LoyaltyRule> $rules */
        $rules = $this->createQueryBuilder('r')
            ->andWhere('r.agency = :agency')
            ->andWhere('r.active = true')
            ->setParameter('agency', $agency)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rules;
    }
}
