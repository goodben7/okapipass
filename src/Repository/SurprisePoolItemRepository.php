<?php

namespace App\Repository;

use App\Entity\SurprisePool;
use App\Entity\SurprisePoolItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurprisePoolItem>
 */
class SurprisePoolItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurprisePoolItem::class);
    }

    /**
     * @return list<SurprisePoolItem>
     */
    public function findActiveForPool(SurprisePool $pool): array
    {
        /** @var list<SurprisePoolItem> $items */
        $items = $this->createQueryBuilder('i')
            ->andWhere('i.pool = :pool')
            ->andWhere('i.active = true')
            ->setParameter('pool', $pool)
            ->orderBy('i.label', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }
}
