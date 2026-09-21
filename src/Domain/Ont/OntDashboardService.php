<?php

namespace App\Domain\Ont;

use App\Entity\Agency;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Entity\IssuedOkapiPass;
use App\Entity\PassDeclaration;
use App\Repository\AgencyPaymentRepository;
use App\Repository\AgencyRepository;
use App\Repository\AgencyTicketRepository;
use App\Repository\IssuedOkapiPassRepository;
use App\Repository\PassDeclarationRepository;

final class OntDashboardService
{
    public function __construct(
        private AgencyRepository $agencies,
        private AgencyTicketRepository $tickets,
        private PassDeclarationRepository $declarations,
        private IssuedOkapiPassRepository $passes,
        private AgencyPaymentRepository $payments,
    ) {
    }

    /**
     * @return array{
     *     periodMonth: string,
     *     generatedAt: string,
     *     kpis: array<string, int|string>,
     *     fptByMonth: list<array{periodMonth: string, draft: int, submitted: int, paid: int, total: int}>,
     *     recentDeclarations: list<array<string, mixed>>,
     *     topAgenciesByFptDue: list<array{agencyId: string, agencyName: string, fptDue: int, currency: string}>,
     *     alerts: list<array{type: string, severity: string, message: string, agencyId: ?string, periodMonth: ?string, declarationId: ?string}>
     * }
     */
    public function build(?string $periodMonth = null): array
    {
        $periodMonth = $this->normalizePeriodMonth($periodMonth);
        $now = new \DateTimeImmutable('now');
        $today = $now->setTime(0, 0);
        $monthStart = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodMonth.'-01');
        if (false === $monthStart) {
            $monthStart = $today->modify('first day of this month')->setTime(0, 0);
            $periodMonth = $monthStart->format('Y-m');
        }
        $monthEnd = $monthStart->modify('first day of next month');

        $agenciesTotal = (int) $this->agencies->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $agenciesActive = (int) $this->agencies->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.status = :status')
            ->setParameter('status', Agency::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $ticketsToday = (int) $this->tickets->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.createdAt >= :start')
            ->andWhere('t.createdAt < :end')
            ->andWhere('t.status != :cancelled')
            ->setParameter('start', $today)
            ->setParameter('end', $today->modify('+1 day'))
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();

        $ticketsMonth = (int) $this->tickets->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.travelDate >= :from')
            ->andWhere('t.travelDate < :to')
            ->andWhere('t.status != :cancelled')
            ->setParameter('from', $monthStart)
            ->setParameter('to', $monthEnd)
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();

        $passesActive = (int) $this->passes->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('(p.expiresAt IS NULL OR p.expiresAt >= :now)')
            ->setParameter('status', IssuedOkapiPass::STATUS_ACTIVE)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        $passesIssuedMonth = (int) $this->passes->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.createdAt >= :from')
            ->andWhere('p.createdAt < :to')
            ->setParameter('from', $monthStart)
            ->setParameter('to', $monthEnd)
            ->getQuery()
            ->getSingleScalarResult();

        $fptTotals = $this->declarations->summarizeNational();

        $paymentsPending = (int) $this->payments->createQueryBuilder('pay')
            ->select('COUNT(pay.id)')
            ->andWhere('pay.status = :pending')
            ->andWhere('pay.channel = :online')
            ->setParameter('pending', AgencyPayment::STATUS_PENDING)
            ->setParameter('online', AgencyPayment::CHANNEL_ONLINE)
            ->getQuery()
            ->getSingleScalarResult();

        $paymentsPaidToday = (int) $this->payments->createQueryBuilder('pay')
            ->select('COUNT(pay.id)')
            ->andWhere('pay.status = :paid')
            ->andWhere('pay.paidAt >= :start')
            ->andWhere('pay.paidAt < :end')
            ->setParameter('paid', AgencyPayment::STATUS_PAID)
            ->setParameter('start', $today)
            ->setParameter('end', $today->modify('+1 day'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'periodMonth' => $periodMonth,
            'generatedAt' => $now->format(\DateTimeInterface::ATOM),
            'kpis' => [
                'agenciesActive' => $agenciesActive,
                'agenciesTotal' => $agenciesTotal,
                'ticketsToday' => $ticketsToday,
                'ticketsMonth' => $ticketsMonth,
                'passesActive' => $passesActive,
                'passesIssuedMonth' => $passesIssuedMonth,
                'fptDraft' => $fptTotals['draft'],
                'fptSubmitted' => $fptTotals['submitted'],
                'fptValidated' => $fptTotals['validated'],
                'fptPaid' => $fptTotals['paid'],
                'fptDue' => $fptTotals['draft'] + $fptTotals['submitted'] + $fptTotals['validated'],
                'currency' => Agency::DEFAULT_CURRENCY,
                'paymentsPending' => $paymentsPending,
                'paymentsPaidToday' => $paymentsPaidToday,
            ],
            'fptByMonth' => $this->declarations->summarizeByPeriodMonth(6),
            'recentDeclarations' => $this->mapRecentDeclarations($this->declarations->findRecentNational(10)),
            'topAgenciesByFptDue' => $this->declarations->topAgenciesByFptDue(5),
            'alerts' => $this->buildAlerts($periodMonth),
        ];
    }

    /**
     * @param list<PassDeclaration> $declarations
     *
     * @return list<array<string, mixed>>
     */
    private function mapRecentDeclarations(array $declarations): array
    {
        $rows = [];
        foreach ($declarations as $decl) {
            $rows[] = [
                'id' => $decl->getId(),
                'label' => $decl->getLabel(),
                'source' => $decl->getSource(),
                'status' => $decl->getStatus(),
                'periodMonth' => $decl->getPeriodMonth(),
                'fptTotal' => $decl->getFptTotal(),
                'currency' => $decl->getCurrency(),
                'agencyId' => $decl->getAgency()?->getId(),
                'agencyName' => $decl->getAgency()?->getName(),
                'submittedAt' => $decl->getSubmittedAt()?->format(\DateTimeInterface::ATOM),
                'createdAt' => $decl->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{type: string, severity: string, message: string, agencyId: ?string, periodMonth: ?string, declarationId: ?string}>
     */
    private function buildAlerts(string $currentPeriod): array
    {
        $alerts = [];

        foreach ($this->declarations->findOpenMonthlyBefore($currentPeriod, 20) as $decl) {
            $period = (string) ($decl->getPeriodMonth() ?: 'période inconnue');
            $agencyName = (string) ($decl->getAgency()?->getName() ?? 'Agence');

            [$type, $severity, $message] = match ($decl->getStatus()) {
                PassDeclaration::STATUS_DRAFT => [
                    'FPT_MONTHLY_DRAFT',
                    'warning',
                    sprintf('%s : déclaration mensuelle %s encore en brouillon.', $agencyName, $period),
                ],
                PassDeclaration::STATUS_SUBMITTED => [
                    'FPT_AWAITING_VALIDATION',
                    'warning',
                    sprintf('%s : FPT %s en attente de validation ONT.', $agencyName, $period),
                ],
                PassDeclaration::STATUS_VALIDATED => [
                    'FPT_AWAITING_PAYMENT',
                    'critical',
                    sprintf('%s : FPT %s validé, paiement en attente.', $agencyName, $period),
                ],
                default => [
                    'FPT_OPEN',
                    'warning',
                    sprintf('%s : FPT %s non soldé.', $agencyName, $period),
                ],
            };

            $alerts[] = [
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
                'agencyId' => $decl->getAgency()?->getId(),
                'periodMonth' => $decl->getPeriodMonth(),
                'declarationId' => $decl->getId(),
            ];
        }

        return $alerts;
    }

    private function normalizePeriodMonth(?string $periodMonth): string
    {
        $periodMonth = trim((string) $periodMonth);
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodMonth)) {
            return $periodMonth;
        }

        return (new \DateTimeImmutable('today'))->format('Y-m');
    }
}
