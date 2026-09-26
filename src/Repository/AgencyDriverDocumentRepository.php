<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyDriverDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyDriverDocument> */
class AgencyDriverDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyDriverDocument::class);
    }

    /**
     * @return list<AgencyDriverDocument>
     */
    public function findExpiring(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        /** @var list<AgencyDriverDocument> $rows */
        $rows = $this->createQueryBuilder('d')
            ->andWhere('d.agency = :agency')
            ->andWhere('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt >= :from')
            ->andWhere('d.expiresAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('d.expiresAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function countExpiring(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.agency = :agency')
            ->andWhere('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt >= :from')
            ->andWhere('d.expiresAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
