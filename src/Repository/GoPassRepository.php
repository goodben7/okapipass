<?php

namespace App\Repository;

use App\Entity\GoPass;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GoPassRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoPass::class);
    }

    public function findActiveRoutier(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        return $this->createQueryBuilder('g')
            ->andWhere('g.transportType = :type')
            ->andWhere('g.active = :active')
            ->setParameter('type', GoPass::TRANSPORT_ROUTIER)
            ->setParameter('active', true)
            ->orderBy('g.price', 'ASC')
            ->addOrderBy('g.label', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
