<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyMaintenanceCase;
use App\Entity\AgencyTransport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgencyMaintenanceCase>
 */
class AgencyMaintenanceCaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyMaintenanceCase::class);
    }

    public function countBlockingByTransport(AgencyTransport $transport): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.transport = :transport')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('transport', $transport)
            ->setParameter('statuses', AgencyMaintenanceCase::blockingStatuses())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBlockingByAgency(Agency $agency): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.agency = :agency')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('agency', $agency)
            ->setParameter('statuses', AgencyMaintenanceCase::blockingStatuses())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumCompletedCostSince(Agency $agency, \DateTimeImmutable $since): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.actualCost), 0)')
            ->where('c.agency = :agency')
            ->andWhere('c.status = :status')
            ->andWhere('c.completedAt >= :since')
            ->setParameter('agency', $agency)
            ->setParameter('status', AgencyMaintenanceCase::STATUS_DONE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * @return list<AgencyMaintenanceCase>
     */
    public function findRecentOpenByAgency(Agency $agency, int $limit): array
    {
        /** @var list<AgencyMaintenanceCase> $rows */
        $rows = $this->createQueryBuilder('c')
            ->where('c.agency = :agency')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('agency', $agency)
            ->setParameter('statuses', AgencyMaintenanceCase::blockingStatuses())
            ->orderBy('c.reportedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
