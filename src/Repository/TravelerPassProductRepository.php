<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\TravelerPassProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TravelerPassProduct>
 */
class TravelerPassProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelerPassProduct::class);
    }

    /**
     * @return list<TravelerPassProduct>
     */
    public function findActiveForAgency(Agency $agency): array
    {
        /** @var list<TravelerPassProduct> $products */
        $products = $this->createQueryBuilder('p')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.active = true')
            ->setParameter('agency', $agency)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $products;
    }

    /**
     * @return list<TravelerPassProduct>
     */
    public function findAllActive(): array
    {
        /** @var list<TravelerPassProduct> $products */
        $products = $this->createQueryBuilder('p')
            ->andWhere('p.active = true')
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $products;
    }
}
