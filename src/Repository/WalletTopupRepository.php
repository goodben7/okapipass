<?php

namespace App\Repository;

use App\Entity\WalletTopup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WalletTopup>
 */
class WalletTopupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletTopup::class);
    }

    public function findOneByProviderTx(string $providerTx): ?WalletTopup
    {
        return $this->findOneBy(['providerTx' => $providerTx]);
    }
}
