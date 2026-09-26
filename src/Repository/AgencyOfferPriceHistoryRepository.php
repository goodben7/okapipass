<?php

namespace App\Repository;

use App\Entity\AgencyOffer;
use App\Entity\AgencyOfferPriceHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyOfferPriceHistory> */
class AgencyOfferPriceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyOfferPriceHistory::class);
    }

    public function findOpenForOffer(AgencyOffer $offer): ?AgencyOfferPriceHistory
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

    public function findEffectivePrice(AgencyOffer $offer, \DateTimeImmutable $at): ?AgencyOfferPriceHistory
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
