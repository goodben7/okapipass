<?php

namespace App\Repository;

use App\Entity\LoyaltyPointLedger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoyaltyPointLedger>
 */
class LoyaltyPointLedgerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoyaltyPointLedger::class);
    }

    public function findOneByTicketAndReason(\App\Entity\AgencyTicket $ticket, string $reason): ?LoyaltyPointLedger
    {
        return $this->findOneBy(['ticket' => $ticket, 'reason' => $reason]);
    }

    public function existsByAccountAndLabel(\App\Entity\LoyaltyAccount $account, string $label): bool
    {
        $count = (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.account = :account')
            ->andWhere('l.label = :label')
            ->setParameter('account', $account)
            ->setParameter('label', $label)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
