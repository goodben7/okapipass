<?php

namespace App\Repository;

use App\Entity\AgencyOffer;
use App\Entity\AgencyOfferScheduleHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyOfferScheduleHistory> */
class AgencyOfferScheduleHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyOfferScheduleHistory::class);
    }

    public function findOpenForOffer(AgencyOffer $offer): ?AgencyOfferScheduleHistory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.offer = :offer')
            ->andWhere('h.effectiveTo IS NULL')
            ->setParameter('offer', $offer)
            ->orderBy('h.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<AgencyOfferScheduleHistory>
     */
    public function findForOffer(AgencyOffer $offer): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.offer = :offer')
            ->setParameter('offer', $offer)
            ->orderBy('h.effectiveFrom', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findEffectiveAt(AgencyOffer $offer, \DateTimeImmutable $at): ?AgencyOfferScheduleHistory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.offer = :offer')
            ->andWhere('h.effectiveFrom <= :at')
            ->andWhere('h.effectiveTo IS NULL OR h.effectiveTo > :at')
            ->setParameter('offer', $offer)
            ->setParameter('at', $at)
            ->orderBy('h.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
