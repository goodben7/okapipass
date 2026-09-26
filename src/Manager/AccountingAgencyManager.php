<?php

namespace App\Manager;

use App\Entity\AccountingDailyClose;
use App\Entity\AccountingJournal;
use App\Entity\Agency;
use App\Entity\AgencyPayment;
use App\Entity\CashHandover;
use App\Entity\User;
use App\Exception\ConflictException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AccountingDailyCloseRepository;
use App\Repository\AccountingJournalRepository;
use App\Repository\AgencyPaymentRepository;
use App\Repository\AgencyTicketRepository;
use App\Repository\CashHandoverRepository;
use App\Repository\PosSessionRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AccountingAgencyManager
{
    public const int VARIANCE_ALERT_THRESHOLD = 5000;
    public const float DEFAULT_PLATFORM_FEE_PERCENT = 0.0;

    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AccountingJournalRepository $journals,
        private AccountingDailyCloseRepository $dailyCloses,
        private AgencyPaymentRepository $payments,
        private CashHandoverRepository $handovers,
        private PosSessionRepository $sessions,
        private AgencyTicketRepository $tickets,
        private float $platformFeePercent = self::DEFAULT_PLATFORM_FEE_PERCENT,
    ) {
    }

    public function recordFromAgencyPayment(AgencyPayment $payment): void
    {
        if (AgencyPayment::STATUS_PAID !== $payment->getStatus()) {
            return;
        }

        if ($this->journals->existsForSource(AccountingJournal::SOURCE_AGENCY_PAYMENT, (string) $payment->getId())) {
            return;
        }

        $agency = $payment->getAgency();
        if (!$agency instanceof Agency) {
            return;
        }

        $occurredAt = $payment->getPaidAt() ?? new \DateTimeImmutable();
        $entryDate = $occurredAt->setTime(0, 0);
        $currency = $payment->getCurrency();
        $gross = $payment->getAmount();
        $ticket = $payment->getTicket();
        $passPrice = max(0, (int) ($ticket?->getPassPrice() ?? 0));
        $passPrice = min($passPrice, $gross);
        $ticketPortion = max(0, $gross - $passPrice);
        $feePercent = $this->platformFeePercent;
        $commission = (int) floor($ticketPortion * $feePercent / 100);

        $booking = $payment->getBooking();
        $labelParts = ['Payment ' . $payment->getReference()];
        if (null !== $booking?->getId()) {
            $labelParts[] = 'booking:' . $booking->getId();
        }
        if (null !== $ticket?->getId()) {
            $labelParts[] = 'ticket:' . $ticket->getId();
        }
        $baseLabel = implode(' ', $labelParts);

        $tender = $this->resolveTenderAccount($payment);
        $this->persistJournal(
            $agency,
            $entryDate,
            $tender,
            AccountingJournal::DIRECTION_DEBIT,
            $gross,
            $currency,
            AccountingJournal::SOURCE_AGENCY_PAYMENT,
            (string) $payment->getId(),
            $baseLabel.' tender',
        );

        if ($ticketPortion > 0) {
            $this->persistJournal(
                $agency,
                $entryDate,
                AccountingJournal::ACCOUNT_SALES,
                AccountingJournal::DIRECTION_CREDIT,
                $ticketPortion,
                $currency,
                AccountingJournal::SOURCE_AGENCY_PAYMENT,
                (string) $payment->getId(),
                $baseLabel.' sales',
            );
        }

        if ($passPrice > 0) {
            $this->persistJournal(
                $agency,
                $entryDate,
                AccountingJournal::ACCOUNT_PASS_ONT,
                AccountingJournal::DIRECTION_CREDIT,
                $passPrice,
                $currency,
                AccountingJournal::SOURCE_AGENCY_PAYMENT,
                (string) $payment->getId(),
                $baseLabel.' pass ONT',
            );
        }

        if ($commission > 0) {
            $this->persistJournal(
                $agency,
                $entryDate,
                AccountingJournal::ACCOUNT_COMMISSION,
                AccountingJournal::DIRECTION_DEBIT,
                $commission,
                $currency,
                AccountingJournal::SOURCE_AGENCY_PAYMENT,
                (string) $payment->getId(),
                $baseLabel.' platform fee',
            );
        }

        $this->em->flush();
    }

    public function recordCashHandoverVariance(CashHandover $handover): void
    {
        $agency = $handover->getAgency();
        if (!$agency instanceof Agency) {
            return;
        }

        $variance = $handover->getVariance();
        if (0 === $variance) {
            return;
        }
        if (abs($variance) <= self::VARIANCE_ALERT_THRESHOLD) {
            return;
        }
        if ($this->journals->existsForSource(AccountingJournal::SOURCE_CASH_HANDOVER, (string) $handover->getId())) {
            return;
        }

        $entryDate = ($handover->getConfirmedAt() ?? new \DateTimeImmutable())->setTime(0, 0);
        $direction = $variance > 0
            ? AccountingJournal::DIRECTION_CREDIT
            : AccountingJournal::DIRECTION_DEBIT;

        $this->persistJournal(
            $agency,
            $entryDate,
            AccountingJournal::ACCOUNT_VARIANCE,
            $direction,
            abs($variance),
            $handover->getCurrency(),
            AccountingJournal::SOURCE_CASH_HANDOVER,
            (string) $handover->getId(),
            sprintf('Cash handover variance %s', $handover->getId()),
        );
        $this->em->flush();
    }

    /**
     * @return list<AccountingJournal>
     */
    public function getJournal(
        Agency $agency,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?string $channel = null,
    ): array {
        return $this->journals->findForAgency($agency, $from, $to, $channel);
    }

    public function closeDay(
        Agency $agency,
        User $closedBy,
        \DateTimeImmutable $businessDate,
        ?string $notes = null,
    ): AccountingDailyClose {
        $businessDate = $businessDate->setTime(0, 0);

        $existing = $this->dailyCloses->findOneByAgencyAndDate($agency, $businessDate);
        if ($existing instanceof AccountingDailyClose && AccountingDailyClose::STATUS_CLOSED === $existing->getStatus()) {
            throw new ConflictException(sprintf('Day %s is already closed.', $businessDate->format('Y-m-d')));
        }

        if ($existing instanceof AccountingDailyClose) {
            return $existing;
        }

        $journals = $this->journals->findForAgency($agency, $businessDate, $businessDate, null);

        $salesTotal = 0;
        $cashTotal = 0;
        $mmTotal = 0;
        $cardTotal = 0;
        $currency = Agency::DEFAULT_CURRENCY;

        foreach ($journals as $journal) {
            $currency = $journal->getCurrency();
            $amount = $journal->getAmount();

            if (AccountingJournal::ACCOUNT_SALES === $journal->getAccount()
                && AccountingJournal::DIRECTION_CREDIT === $journal->getDirection()) {
                $salesTotal += $amount;
                continue;
            }

            // New model: tender DEBIT; legacy model: tender CREDIT
            $isTender = \in_array($journal->getAccount(), [
                AccountingJournal::ACCOUNT_CASH,
                AccountingJournal::ACCOUNT_MM,
                AccountingJournal::ACCOUNT_CARD,
            ], true);
            if (!$isTender) {
                continue;
            }

            match ($journal->getAccount()) {
                AccountingJournal::ACCOUNT_CASH => $cashTotal += $amount,
                AccountingJournal::ACCOUNT_MM => $mmTotal += $amount,
                AccountingJournal::ACCOUNT_CARD => $cardTotal += $amount,
                default => null,
            };
        }

        if (0 === $salesTotal) {
            $salesTotal = $cashTotal + $mmTotal + $cardTotal;
        }

        $close = new AccountingDailyClose();
        $close->setAgency($agency);
        $close->setCloseDate($businessDate);
        $close->setStatus(AccountingDailyClose::STATUS_CLOSED);
        $close->setSalesTotal($salesTotal);
        $close->setCashTotal($cashTotal);
        $close->setMmTotal($mmTotal);
        $close->setCardTotal($cardTotal);
        $close->setCurrency($currency);
        $close->setNotes($notes);
        $close->setClosedBy($closedBy);
        $close->setClosedAt(new \DateTimeImmutable());

        $this->em->persist($close);
        $this->em->flush();

        return $close;
    }

    public function requireAgency(): Agency
    {
        return $this->agencyContext->requireAgency();
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     paidPaymentsCount: int,
     *     paidAmount: int,
     *     journalCreditCount: int,
     *     journalCreditAmount: int,
     *     unmatchedPaymentIds: list<string>,
     *     unmatchedJournalSourceIds: list<string>
     * }
     */
    public function reconcile(
        Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        if ($from > $to) {
            throw new UnprocessableEntityException('Parameter "from" must be before or equal to "to".');
        }

        $payments = $this->payments->findPaidForAgencyBetween($agency, $from, $to);
        $journals = $this->journals->findAgencyPaymentCreditsForAgencyBetween($agency, $from, $to);

        $paidAmount = 0;
        $paymentIds = [];
        foreach ($payments as $payment) {
            $paidAmount += $payment->getAmount();
            if (null !== $payment->getId()) {
                $paymentIds[(string) $payment->getId()] = (string) $payment->getId();
            }
        }

        $journalAmount = 0;
        $journalSourceIds = [];
        foreach ($journals as $journal) {
            $journalAmount += $journal->getAmount();
            if (null !== $journal->getSourceId()) {
                $journalSourceIds[$journal->getSourceId()] = $journal->getSourceId();
            }
        }

        $unmatchedPaymentIds = array_values(array_diff($paymentIds, $journalSourceIds));
        $unmatchedJournalSourceIds = array_values(array_diff($journalSourceIds, $paymentIds));
        sort($unmatchedPaymentIds);
        sort($unmatchedJournalSourceIds);

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'paidPaymentsCount' => \count($payments),
            'paidAmount' => $paidAmount,
            'journalCreditCount' => \count($journals),
            'journalCreditAmount' => $journalAmount,
            'unmatchedPaymentIds' => $unmatchedPaymentIds,
            'unmatchedJournalSourceIds' => $unmatchedJournalSourceIds,
        ];
    }

    /**
     * @return array{from: string, to: string, ca: int, passOnt: int, commission: int, net: int, currency: string}
     */
    public function marginReport(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        if ($from > $to) {
            throw new UnprocessableEntityException('Parameter "from" must be before or equal to "to".');
        }

        $journals = $this->journals->findForAgency($agency, $from, $to, null);
        $ca = 0;
        $passOnt = 0;
        $commission = 0;
        $currency = Agency::DEFAULT_CURRENCY;

        foreach ($journals as $journal) {
            $currency = $journal->getCurrency();
            $amount = $journal->getAmount();
            if (AccountingJournal::ACCOUNT_SALES === $journal->getAccount()
                && AccountingJournal::DIRECTION_CREDIT === $journal->getDirection()) {
                $ca += $amount;
            }
            if (AccountingJournal::ACCOUNT_PASS_ONT === $journal->getAccount()
                && AccountingJournal::DIRECTION_CREDIT === $journal->getDirection()) {
                $passOnt += $amount;
            }
            if (AccountingJournal::ACCOUNT_COMMISSION === $journal->getAccount()
                && AccountingJournal::DIRECTION_DEBIT === $journal->getDirection()) {
                $commission += $amount;
            }
        }

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'ca' => $ca,
            'passOnt' => $passOnt,
            'commission' => $commission,
            'net' => $ca - $commission,
            'currency' => $currency,
        ];
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     depots: list<array{depotCode: string, depotLabel: string, salesTotal: int, cashTotal: int, mmTotal: int, cardTotal: int, closesCount: int}>
     * }
     */
    public function consolidation(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        if ($from > $to) {
            throw new UnprocessableEntityException('Parameter "from" must be before or equal to "to".');
        }

        $closes = $this->dailyCloses->findForAgencyBetween($agency, $from, $to);
        /** @var array<string, array{depotCode: string, depotLabel: string, salesTotal: int, cashTotal: int, mmTotal: int, cardTotal: int, closesCount: int}> $byDepot */
        $byDepot = [];

        foreach ($closes as $close) {
            $depot = $close->getDepot();
            $code = $depot?->getCode() ?? 'DEFAULT';
            $label = $depot?->getLabel() ?? 'DEFAULT';
            if (!isset($byDepot[$code])) {
                $byDepot[$code] = [
                    'depotCode' => $code,
                    'depotLabel' => $label,
                    'salesTotal' => 0,
                    'cashTotal' => 0,
                    'mmTotal' => 0,
                    'cardTotal' => 0,
                    'closesCount' => 0,
                ];
            }
            $byDepot[$code]['salesTotal'] += $close->getSalesTotal();
            $byDepot[$code]['cashTotal'] += $close->getCashTotal();
            $byDepot[$code]['mmTotal'] += $close->getMmTotal();
            $byDepot[$code]['cardTotal'] += $close->getCardTotal();
            ++$byDepot[$code]['closesCount'];
        }

        // Sessions without closes still contribute DEFAULT bucket presence
        if ([] === $byDepot) {
            $byDepot['DEFAULT'] = [
                'depotCode' => 'DEFAULT',
                'depotLabel' => 'DEFAULT',
                'salesTotal' => 0,
                'cashTotal' => 0,
                'mmTotal' => 0,
                'cardTotal' => 0,
                'closesCount' => 0,
            ];
            $journals = $this->journals->findForAgency($agency, $from, $to, null);
            foreach ($journals as $journal) {
                if (AccountingJournal::ACCOUNT_SALES === $journal->getAccount()
                    && AccountingJournal::DIRECTION_CREDIT === $journal->getDirection()) {
                    $byDepot['DEFAULT']['salesTotal'] += $journal->getAmount();
                }
                if (AccountingJournal::DIRECTION_DEBIT === $journal->getDirection()
                    || AccountingJournal::DIRECTION_CREDIT === $journal->getDirection()) {
                    match ($journal->getAccount()) {
                        AccountingJournal::ACCOUNT_CASH => $byDepot['DEFAULT']['cashTotal'] += $journal->getAmount(),
                        AccountingJournal::ACCOUNT_MM => $byDepot['DEFAULT']['mmTotal'] += $journal->getAmount(),
                        AccountingJournal::ACCOUNT_CARD => $byDepot['DEFAULT']['cardTotal'] += $journal->getAmount(),
                        default => null,
                    };
                }
            }
        }

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'depots' => array_values($byDepot),
        ];
    }

    /**
     * @return array{
     *     cashVariances: list<array<string, mixed>>,
     *     agedPendingPayments: list<array<string, mixed>>,
     *     cancelRateSpike: array{rate: float, cancelled: int, total: int}|null
     * }
     */
    public function alerts(Agency $agency): array
    {
        $since = (new \DateTimeImmutable('-14 days'))->setTime(0, 0);
        $variances = [];
        foreach ($this->handovers->findConfirmedWithVarianceSince($agency, $since) as $handover) {
            $variances[] = [
                'id' => $handover->getId(),
                'declaredAmount' => $handover->getDeclaredAmount(),
                'expectedCash' => $handover->getExpectedCash(),
                'variance' => $handover->getVariance(),
                'confirmedAt' => $handover->getConfirmedAt()?->format(\DateTimeInterface::ATOM),
                'sellerId' => $handover->getSeller()?->getId(),
            ];
        }

        $aged = [];
        $threshold = new \DateTimeImmutable('-24 hours');
        foreach ($this->payments->findPendingOlderThan($agency, $threshold) as $payment) {
            $aged[] = [
                'id' => $payment->getId(),
                'reference' => $payment->getReference(),
                'amount' => $payment->getAmount(),
                'createdAt' => $payment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'status' => $payment->getStatus(),
            ];
        }

        $cancelSpike = $this->computeCancelRateSpike($agency);

        return [
            'cashVariances' => $variances,
            'agedPendingPayments' => $aged,
            'cancelRateSpike' => $cancelSpike,
        ];
    }

    /**
     * @return array{rate: float, cancelled: int, total: int, priorRate: float, priorCancelled: int, priorTotal: int}|null
     */
    public function computeCancelRateSpike(Agency $agency): ?array
    {
        $today = new \DateTimeImmutable('today');
        $currentFrom = $today->modify('-6 days')->setTime(0, 0);
        $currentTo = $today->setTime(23, 59, 59);
        $priorFrom = $today->modify('-13 days')->setTime(0, 0);
        $priorTo = $today->modify('-7 days')->setTime(23, 59, 59);

        $current = $this->ticketCancelStats($agency, $currentFrom, $currentTo);
        $prior = $this->ticketCancelStats($agency, $priorFrom, $priorTo);

        if ($current['total'] < 1) {
            return null;
        }

        $rate = $current['rate'];
        $priorRate = $prior['rate'];
        $spike = $rate > 15.0 || ($prior['total'] >= 1 && $priorRate > 0 && $rate >= 2 * $priorRate);
        if (!$spike) {
            return [
                'rate' => $rate,
                'cancelled' => $current['cancelled'],
                'total' => $current['total'],
                'priorRate' => $priorRate,
                'priorCancelled' => $prior['cancelled'],
                'priorTotal' => $prior['total'],
                'spike' => false,
            ];
        }

        return [
            'rate' => $rate,
            'cancelled' => $current['cancelled'],
            'total' => $current['total'],
            'priorRate' => $priorRate,
            'priorCancelled' => $prior['cancelled'],
            'priorTotal' => $prior['total'],
            'spike' => true,
        ];
    }

    /**
     * @return array{cancelled: int, total: int, rate: float}
     */
    public function ticketCancelStats(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $total = (int) $this->tickets->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.createdAt >= :from')
            ->andWhere('t.createdAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        $cancelled = (int) $this->tickets->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.createdAt >= :from')
            ->andWhere('t.createdAt <= :to')
            ->andWhere('t.status = :cancelled')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('cancelled', \App\Entity\AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();

        $rate = $total > 0 ? round(100 * $cancelled / $total, 2) : 0.0;

        return ['cancelled' => $cancelled, 'total' => $total, 'rate' => $rate];
    }

    /**
     * @return array{
     *     date: string,
     *     ticketsCount: int,
     *     salesCount: int,
     *     caTotal: int,
     *     mix: array{CASH: int, MM: int, CARD: int},
     *     bySeller: list<array{sellerId: string, tickets: int, amount: int}>,
     *     byPointOfSale: list<array{pointOfSale: string, tickets: int, amount: int}>
     * }
     */
    public function posKpi(Agency $agency, \DateTimeImmutable $date): array
    {
        $from = $date->setTime(0, 0);
        $to = $date->setTime(23, 59, 59);
        $payments = $this->payments->findPaidPosForAgencyBetween($agency, $from, $to);

        $mix = ['CASH' => 0, 'MM' => 0, 'CARD' => 0];
        $bySeller = [];
        $byPos = [];
        $caTotal = 0;
        $ticketsCount = 0;

        foreach ($payments as $payment) {
            $amount = $payment->getAmount();
            $caTotal += $amount;
            if (null !== $payment->getTicket()) {
                ++$ticketsCount;
            }

            $methodKey = match ($payment->getMethod()) {
                AgencyPayment::METHOD_CASH => 'CASH',
                AgencyPayment::METHOD_MOBILE_MONEY => 'MM',
                AgencyPayment::METHOD_CARD => 'CARD',
                default => 'CASH',
            };
            $mix[$methodKey] = ($mix[$methodKey] ?? 0) + $amount;

            $sessionNote = (string) $payment->getNotes();
            $sellerId = 'unknown';
            $pointOfSale = 'DEFAULT';
            if (preg_match('/POS session (PS\w+)/', $sessionNote, $m)) {
                $session = $this->sessions->find($m[1]);
                if (null !== $session) {
                    $sellerId = (string) ($session->getSeller()?->getId() ?? 'unknown');
                    $pointOfSale = $session->getPointOfSale() ?: 'DEFAULT';
                }
            }

            if (!isset($bySeller[$sellerId])) {
                $bySeller[$sellerId] = ['sellerId' => $sellerId, 'tickets' => 0, 'amount' => 0];
            }
            $bySeller[$sellerId]['tickets'] += null !== $payment->getTicket() ? 1 : 0;
            $bySeller[$sellerId]['amount'] += $amount;

            if (!isset($byPos[$pointOfSale])) {
                $byPos[$pointOfSale] = ['pointOfSale' => $pointOfSale, 'tickets' => 0, 'amount' => 0];
            }
            $byPos[$pointOfSale]['tickets'] += null !== $payment->getTicket() ? 1 : 0;
            $byPos[$pointOfSale]['amount'] += $amount;
        }

        return [
            'date' => $date->format('Y-m-d'),
            'ticketsCount' => $ticketsCount,
            'salesCount' => \count($payments),
            'caTotal' => $caTotal,
            'mix' => $mix,
            'bySeller' => array_values($bySeller),
            'byPointOfSale' => array_values($byPos),
        ];
    }

    private function resolveTenderAccount(AgencyPayment $payment): string
    {
        return match ($payment->getMethod()) {
            AgencyPayment::METHOD_CASH => AccountingJournal::ACCOUNT_CASH,
            AgencyPayment::METHOD_MOBILE_MONEY => AccountingJournal::ACCOUNT_MM,
            AgencyPayment::METHOD_CARD => AccountingJournal::ACCOUNT_CARD,
            default => AccountingJournal::ACCOUNT_SALES,
        };
    }

    private function persistJournal(
        Agency $agency,
        \DateTimeImmutable $entryDate,
        string $account,
        string $direction,
        int $amount,
        string $currency,
        string $sourceType,
        ?string $sourceId,
        string $label,
    ): void {
        if ($amount <= 0) {
            return;
        }

        $journal = new AccountingJournal();
        $journal->setAgency($agency);
        $journal->setEntryDate($entryDate);
        $journal->setAccount($account);
        $journal->setDirection($direction);
        $journal->setAmount($amount);
        $journal->setCurrency($currency);
        $journal->setSourceType($sourceType);
        $journal->setSourceId($sourceId);
        $journal->setLabel(substr($label, 0, 160));
        $this->em->persist($journal);
    }
}
