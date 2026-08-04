<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgencyOffer>
 */
class AgencyOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyOffer::class);
    }

    public function countActiveByTransport(AgencyTransport $transport): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.transport = :transport')
            ->andWhere('o.active = :active')
            ->setParameter('transport', $transport)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByTransport(AgencyTransport $transport): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.transport = :transport')
            ->setParameter('transport', $transport)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<AgencyOffer>
     */
    public function findActiveByAgency(Agency $agency): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.agency = :agency')
            ->andWhere('o.active = :active')
            ->setParameter('agency', $agency)
            ->setParameter('active', true)
            ->orderBy('o.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
