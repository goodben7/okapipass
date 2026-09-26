<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\SellerBonusLedger;
use App\Entity\SellerCommissionRule;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SellerBonusLedger> */
class SellerBonusLedgerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SellerBonusLedger::class);
    }

    public function findOneForSellerPeriodRule(
        Agency $agency,
        User $seller,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        SellerCommissionRule $rule,
    ): ?SellerBonusLedger {
        return $this->findOneBy([
            'agency' => $agency,
            'seller' => $seller,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'rule' => $rule,
        ]);
    }
}
