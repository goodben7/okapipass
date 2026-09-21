<?php

namespace App\Repository;

use App\Entity\AgencyObligationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyObligationType> */
class AgencyObligationTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyObligationType::class);
    }

    public function findOneByCode(string $code): ?AgencyObligationType
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }

    /**
     * @return list<AgencyObligationType>
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
