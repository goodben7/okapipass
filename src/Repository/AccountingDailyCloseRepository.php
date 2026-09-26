<?php

namespace App\Repository;

use App\Entity\AccountingDailyClose;
use App\Entity\Agency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountingDailyClose>
 */
class AccountingDailyCloseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountingDailyClose::class);
    }

    public function findOneByAgencyAndDate(Agency $agency, \DateTimeImmutable $closeDate): ?AccountingDailyClose
    {
        return $this->findOneBy(['agency' => $agency, 'closeDate' => $closeDate]);
    }

    /** @return list<AccountingDailyClose> */
    public function findForAgencyBetween(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<AccountingDailyClose> $rows */
        $rows = $this->createQueryBuilder('c')
            ->andWhere('c.agency = :agency')
            ->andWhere('c.closeDate >= :from')
            ->andWhere('c.closeDate <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->orderBy('c.closeDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}

