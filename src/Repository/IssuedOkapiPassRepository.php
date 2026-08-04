<?php

namespace App\Repository;

use App\Entity\IssuedOkapiPass;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<IssuedOkapiPass> */
class IssuedOkapiPassRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssuedOkapiPass::class);
    }

    public function findOneByReference(string $reference): ?IssuedOkapiPass
    {
        return $this->findOneBy(['reference' => strtoupper(trim($reference))]);
    }
}
