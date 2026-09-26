<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyBlacklistEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyBlacklistEntry> */
class AgencyBlacklistEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyBlacklistEntry::class);
    }

    public function findActiveMatch(Agency $agency, string $type, string $value): ?AgencyBlacklistEntry
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.agency = :agency')
            ->andWhere('b.type = :type')
            ->andWhere('LOWER(b.value) = :value')
            ->andWhere('b.active = true')
            ->setParameter('agency', $agency)
            ->setParameter('type', strtoupper(trim($type)))
            ->setParameter('value', strtolower(trim($value)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
