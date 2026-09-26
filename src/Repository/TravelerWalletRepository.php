<?php

namespace App\Repository;

use App\Entity\TravelerWallet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TravelerWallet>
 */
class TravelerWalletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelerWallet::class);
    }

    public function findOneByUserAndCurrency(User $user, string $currency): ?TravelerWallet
    {
        return $this->findOneBy(['user' => $user, 'currency' => $currency]);
    }
}
