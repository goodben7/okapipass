<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyParcel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyParcel> */
class AgencyParcelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyParcel::class);
    }

    public function existsTrackingCode(Agency $agency, string $trackingCode): bool
    {
        $count = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.trackingCode = :code')
            ->setParameter('agency', $agency)
            ->setParameter('code', strtoupper($trackingCode))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
