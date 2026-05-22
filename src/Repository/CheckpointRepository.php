<?php

namespace App\Repository;

use App\Entity\Checkpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CheckpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checkpoint::class);
    }

    public function searchActiveByLabel(string $q, int $limit = 5): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $limit = max(1, min(20, $limit));

        return $this->createQueryBuilder('c')
            ->andWhere('c.active = :active')
            ->andWhere('LOWER(c.label) LIKE :q')
            ->setParameter('active', true)
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->orderBy('c.label', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findActiveByProvinceId(string $provinceId, int $limit = 60): array
    {
        $provinceId = trim($provinceId);
        if ($provinceId === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));

        return $this->createQueryBuilder('c')
            ->innerJoin('c.province', 'p')
            ->andWhere('c.active = :active')
            ->andWhere('p.id = :provinceId')
            ->setParameter('active', true)
            ->setParameter('provinceId', $provinceId)
            ->orderBy('c.label', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
