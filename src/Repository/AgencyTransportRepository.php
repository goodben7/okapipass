<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyTransport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgencyTransport>
 */
class AgencyTransportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyTransport::class);
    }

    public function findOneByAgencyAndPlate(Agency $agency, string $plateNumber): ?AgencyTransport
    {
        return $this->findOneBy([
            'agency' => $agency,
            'plateNumber' => strtoupper(trim($plateNumber)),
        ]);
    }

    public function countByAgency(Agency $agency): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.agency = :agency')
            ->setParameter('agency', $agency)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByAgencyAndStatus(Agency $agency, string $status): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.agency = :agency')
            ->andWhere('t.status = :status')
            ->setParameter('agency', $agency)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
