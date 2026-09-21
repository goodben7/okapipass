<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyObligation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyObligation> */
class AgencyObligationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyObligation::class);
    }

    /**
     * @return list<AgencyObligation>
     */
    public function findForAgencyInRange(
        Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.type', 't')->addSelect('t')
            ->andWhere('o.agency = :agency')
            ->andWhere('o.dueDate >= :from')
            ->andWhere('o.dueDate <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('o.dueDate', 'ASC')
            ->addOrderBy('o.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AgencyObligation>
     */
    public function findOpenForAgency(Agency $agency): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.type', 't')->addSelect('t')
            ->andWhere('o.agency = :agency')
            ->andWhere('o.status = :open')
            ->setParameter('agency', $agency)
            ->setParameter('open', AgencyObligation::STATUS_OPEN)
            ->orderBy('o.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countOpenByTypeCode(Agency $agency, string $typeCode): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->innerJoin('o.type', 't')
            ->andWhere('o.agency = :agency')
            ->andWhere('o.status = :open')
            ->andWhere('t.code = :code')
            ->setParameter('agency', $agency)
            ->setParameter('open', AgencyObligation::STATUS_OPEN)
            ->setParameter('code', strtoupper(trim($typeCode)))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
