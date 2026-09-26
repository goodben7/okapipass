<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\PosSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PosSession>
 */
class PosSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosSession::class);
    }

    public function findOpenForSeller(Agency $agency, User $seller): ?PosSession
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.agency = :agency')
            ->andWhere('s.seller = :seller')
            ->andWhere('s.status = :status')
            ->setParameter('agency', $agency)
            ->setParameter('seller', $seller)
            ->setParameter('status', PosSession::STATUS_OPEN)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
