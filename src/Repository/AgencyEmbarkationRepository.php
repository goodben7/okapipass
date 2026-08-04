<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyEmbarkation> */
class AgencyEmbarkationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyEmbarkation::class);
    }

    public function findOneForOfferOnDate(AgencyOffer $offer, \DateTimeImmutable $date): ?AgencyEmbarkation
    {
        return $this->findOneBy([
            'offer' => $offer,
            'departureDate' => $date,
        ], ['createdAt' => 'DESC']);
    }
}
