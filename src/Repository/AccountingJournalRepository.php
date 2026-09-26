<?php

namespace App\Repository;

use App\Entity\AccountingJournal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountingJournal>
 */
class AccountingJournalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountingJournal::class);
    }

    public function existsForSource(string $sourceType, string $sourceId): bool
    {
        $count = (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.sourceType = :sourceType')
            ->andWhere('j.sourceId = :sourceId')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('sourceId', $sourceId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return list<AccountingJournal>
     */
    public function findForAgency(
        \App\Entity\Agency $agency,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?string $account = null,
    ): array {
        $qb = $this->createQueryBuilder('j')
            ->andWhere('j.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('j.entryDate', 'ASC')
            ->addOrderBy('j.createdAt', 'ASC');

        if (null !== $from) {
            $qb->andWhere('j.entryDate >= :from')->setParameter('from', $from->setTime(0, 0));
        }
        if (null !== $to) {
            $qb->andWhere('j.entryDate <= :to')->setParameter('to', $to->setTime(0, 0));
        }
        if (null !== $account && '' !== $account) {
            $qb->andWhere('j.account = :account')->setParameter('account', $account);
        }

        /** @var list<AccountingJournal> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * @return list<AccountingJournal>
     */
    public function findAgencyPaymentCreditsForAgencyBetween(
        \App\Entity\Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $qb = $this->createQueryBuilder('j')
            ->andWhere('j.agency = :agency')
            ->andWhere('j.sourceType = :sourceType')
            ->andWhere('j.direction = :direction')
            ->andWhere('j.entryDate >= :from')
            ->andWhere('j.entryDate <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('sourceType', AccountingJournal::SOURCE_AGENCY_PAYMENT)
            ->setParameter('direction', AccountingJournal::DIRECTION_CREDIT)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->orderBy('j.entryDate', 'ASC')
            ->addOrderBy('j.createdAt', 'ASC');

        /** @var list<AccountingJournal> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }
}
