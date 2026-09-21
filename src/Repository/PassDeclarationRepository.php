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

    public function findOneMonthlyForAgency(Agency $agency, string $periodMonth): ?PassDeclaration
    {
        return $this->findOneBy([
            'agency' => $agency,
            'source' => PassDeclaration::SOURCE_MONTHLY,
            'periodMonth' => $periodMonth,
        ]);
    }

    /**
     * @return array{draft: int, submitted: int, paid: int}
     */
    public function summarizeNational(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.status AS status, SUM(d.fptTotal) AS total')
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

        return [
            'draft' => $draft,
            'submitted' => $submitted,
            'paid' => $paid,
        ];
    }

    /**
     * @return list<array{periodMonth: string, draft: int, submitted: int, paid: int, total: int}>
     */
    public function summarizeByPeriodMonth(int $limit = 6): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.periodMonth AS periodMonth, d.status AS status, SUM(d.fptTotal) AS total')
            ->andWhere('d.periodMonth IS NOT NULL')
            ->groupBy('d.periodMonth, d.status')
            ->orderBy('d.periodMonth', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $byMonth = [];
        foreach ($rows as $row) {
            $month = (string) ($row['periodMonth'] ?? '');
            if ('' === $month) {
                continue;
            }
            if (!isset($byMonth[$month])) {
                $byMonth[$month] = [
                    'periodMonth' => $month,
                    'draft' => 0,
                    'submitted' => 0,
                    'paid' => 0,
                    'total' => 0,
                ];
            }
            $sum = (int) ($row['total'] ?? 0);
            match ($row['status']) {
                PassDeclaration::STATUS_DRAFT => $byMonth[$month]['draft'] = $sum,
                PassDeclaration::STATUS_SUBMITTED => $byMonth[$month]['submitted'] = $sum,
                PassDeclaration::STATUS_PAID => $byMonth[$month]['paid'] = $sum,
                default => null,
            };
            $byMonth[$month]['total'] = $byMonth[$month]['draft']
                + $byMonth[$month]['submitted']
                + $byMonth[$month]['paid'];
        }

        return \array_slice(array_values($byMonth), 0, max(1, $limit));
    }

    /**
     * @return list<PassDeclaration>
     */
    public function findRecentNational(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.agency', 'a')->addSelect('a')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{agencyId: string, agencyName: string, fptDue: int, currency: string}>
     */
    public function topAgenciesByFptDue(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('a.id AS agencyId, a.name AS agencyName, d.currency AS currency, SUM(d.fptTotal) AS fptDue')
            ->innerJoin('d.agency', 'a')
            ->andWhere('d.status IN (:due)')
            ->setParameter('due', [PassDeclaration::STATUS_DRAFT, PassDeclaration::STATUS_SUBMITTED])
            ->groupBy('a.id, a.name, d.currency')
            ->orderBy('fptDue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'agencyId' => (string) ($row['agencyId'] ?? ''),
                'agencyName' => (string) ($row['agencyName'] ?? ''),
                'fptDue' => (int) ($row['fptDue'] ?? 0),
                'currency' => (string) ($row['currency'] ?? Agency::DEFAULT_CURRENCY),
            ];
        }

        return $result;
    }

    /**
     * Monthly declarations still open (draft/submitted) for periods before $beforePeriod.
     *
     * @return list<PassDeclaration>
     */
    public function findOpenMonthlyBefore(string $beforePeriod, int $limit = 20): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.agency', 'a')->addSelect('a')
            ->andWhere('d.source = :monthly')
            ->andWhere('d.periodMonth IS NOT NULL')
            ->andWhere('d.periodMonth < :before')
            ->andWhere('d.status IN (:open)')
            ->setParameter('monthly', PassDeclaration::SOURCE_MONTHLY)
            ->setParameter('before', $beforePeriod)
            ->setParameter('open', [PassDeclaration::STATUS_DRAFT, PassDeclaration::STATUS_SUBMITTED])
            ->orderBy('d.periodMonth', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
