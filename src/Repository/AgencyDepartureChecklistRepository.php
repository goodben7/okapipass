<?php

namespace App\Repository;

use App\Entity\AgencyDepartureChecklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyDepartureChecklist> */
class AgencyDepartureChecklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyDepartureChecklist::class);
    }
}
