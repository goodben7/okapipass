<?php

namespace App\Repository;

use App\Entity\AgencyTransport;
use App\Entity\AgencyWorkOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyWorkOrder> */
class AgencyWorkOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyWorkOrder::class);
    }

    public function countImmobilizingByTransport(AgencyTransport $transport): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.transport = :transport')
            ->andWhere('w.immobilize = true')
            ->andWhere('w.status IN (:statuses)')
            ->setParameter('transport', $transport)
            ->setParameter('statuses', AgencyWorkOrder::openStatuses())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
