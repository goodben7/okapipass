<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\LoyaltyAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoyaltyAccount>
 */
class LoyaltyAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoyaltyAccount::class);
    }

    public function findOneByAgencyAndPhone(Agency $agency, string $phone): ?LoyaltyAccount
    {
        return $this->findOneBy(['agency' => $agency, 'phone' => $phone]);
    }
}
