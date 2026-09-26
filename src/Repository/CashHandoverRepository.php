<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\CashHandover;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashHandover>
 */
class CashHandoverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashHandover::class);
    }

    /** @return list<CashHandover> */
    public function findConfirmedWithVarianceSince(Agency $agency, \DateTimeImmutable $since): array
    {
        /** @var list<CashHandover> $rows */
        $rows = $this->createQueryBuilder('h')
            ->andWhere('h.agency = :agency')
            ->andWhere('h.status = :confirmed')
            ->andWhere('h.variance != 0')
            ->andWhere('h.confirmedAt >= :since')
            ->setParameter('agency', $agency)
            ->setParameter('confirmed', CashHandover::STATUS_CONFIRMED)
            ->setParameter('since', $since)
            ->orderBy('h.confirmedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
