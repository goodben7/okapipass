<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\PassDeclaration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PassDeclaration> */
class PassDeclarationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PassDeclaration::class);
    }

    /**
     * @return array{fptDue: int, currency: string, draft: int, submitted: int, paid: int, byCurrency: array<string, int>}
     */
    public function summarizeForAgency(Agency $agency): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.status AS status, SUM(d.fptTotal) AS total')
            ->andWhere('d.agency = :agency')
            ->setParameter('agency', $agency)
            ->groupBy('d.status')
            ->getQuery()
            ->getArrayResult();

        $draft = 0;
        $submitted = 0;
        $paid = 0;
        foreach ($rows as $row) {
            $sum = (int) ($row['total'] ?? 0);
            match ($row['status']) {
                PassDeclaration::STATUS_DRAFT => $draft = $sum,
                PassDeclaration::STATUS_SUBMITTED => $submitted = $sum,
                PassDeclaration::STATUS_PAID => $paid = $sum,
                default => null,
            };
        }

        $byCurrencyRows = $this->createQueryBuilder('d')
            ->select('d.currency AS currency, SUM(d.fptTotal) AS total')
            ->andWhere('d.agency = :agency')
            ->andWhere('d.status IN (:due)')
            ->setParameter('agency', $agency)
            ->setParameter('due', [PassDeclaration::STATUS_DRAFT, PassDeclaration::STATUS_SUBMITTED])
            ->groupBy('d.currency')
            ->getQuery()
            ->getArrayResult();

        $byCurrency = [];
        foreach ($byCurrencyRows as $row) {
            $currency = (string) ($row['currency'] ?? $agency->getDefaultCurrency());
            $byCurrency[$currency] = (int) ($row['total'] ?? 0);
        }

        return [
            'fptDue' => $draft + $submitted,
            'currency' => $agency->getDefaultCurrency(),
            'draft' => $draft,
            'submitted' => $submitted,
            'paid' => $paid,
            'byCurrency' => $byCurrency,
        ];
    }

    /**
     * @return list<PassDeclaration>
     */
    public function findRecent(Agency $agency, int $limit = 5): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
