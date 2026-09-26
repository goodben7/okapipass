<?php

namespace App\Repository;

use App\Entity\OtpChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OtpChallenge>
 */
class OtpChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OtpChallenge::class);
    }

    public function findLatestOpen(string $phone, string $purpose): ?OtpChallenge
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.phone = :phone')
            ->andWhere('c.purpose = :purpose')
            ->andWhere('c.consumedAt IS NULL')
            ->setParameter('phone', $phone)
            ->setParameter('purpose', $purpose)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
