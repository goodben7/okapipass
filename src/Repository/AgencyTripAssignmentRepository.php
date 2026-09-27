<?php

namespace App\Repository;

use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyTripAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyTripAssignment> */
class AgencyTripAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyTripAssignment::class);
    }

    public function findOpenForEmbarkation(AgencyEmbarkation $embarkation): ?AgencyTripAssignment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.embarkation = :embarkation')
            ->andWhere('a.unassignedAt IS NULL')
            ->setParameter('embarkation', $embarkation)
            ->orderBy('a.assignedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
