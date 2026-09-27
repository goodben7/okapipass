<?php

namespace App\Repository;

use App\Entity\SchoolContract;
use App\Entity\SchoolInvoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SchoolInvoice> */
class SchoolInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolInvoice::class);
    }

    public function findOneByContractAndPeriod(SchoolContract $contract, string $periodYm): ?SchoolInvoice
    {
        return $this->findOneBy([
            'contract' => $contract,
            'periodYm' => $periodYm,
        ]);
    }
}
