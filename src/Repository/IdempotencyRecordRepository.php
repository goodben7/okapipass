<?php

namespace App\Repository;

use App\Entity\IdempotencyRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IdempotencyRecord> */
class IdempotencyRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdempotencyRecord::class);
    }

    public function findByScopeAndKey(string $scope, string $keyHash): ?IdempotencyRecord
    {
        return $this->findOneBy(['scope' => $scope, 'keyHash' => $keyHash]);
    }
}
