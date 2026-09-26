<?php

namespace App\Repository;

use App\Entity\WalletLedger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WalletLedger>
 */
class WalletLedgerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletLedger::class);
    }

    /**
     * @return list<WalletLedger>
     */
    public function findRecentForWallet(\App\Entity\TravelerWallet $wallet, int $limit = 50): array
    {
        /** @var list<WalletLedger> $rows */
        $rows = $this->createQueryBuilder('l')
            ->andWhere('l.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(\max(1, $limit))
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
