<?php

namespace App\Repository;

use App\Entity\TravelerBeneficiary;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TravelerBeneficiary> */
class TravelerBeneficiaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelerBeneficiary::class);
    }

    /** @return list<TravelerBeneficiary> */
    public function findByOwner(User $owner): array
    {
        /** @var list<TravelerBeneficiary> $rows */
        $rows = $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
