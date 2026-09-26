<?php

namespace App\Repository;

use App\Entity\AgencyBaggageExcess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyBaggageExcess> */
class AgencyBaggageExcessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyBaggageExcess::class);
    }
}
