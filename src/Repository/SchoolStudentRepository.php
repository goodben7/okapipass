<?php

namespace App\Repository;

use App\Entity\SchoolContract;
use App\Entity\SchoolStudent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SchoolStudent> */
class SchoolStudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolStudent::class);
    }

    /**
     * @return list<SchoolStudent>
     */
    public function findActiveByContract(SchoolContract $contract): array
    {
        /** @var list<SchoolStudent> $rows */
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.contract = :contract')
            ->andWhere('s.active = :active')
            ->setParameter('contract', $contract)
            ->setParameter('active', true)
            ->orderBy('s.fullName', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
