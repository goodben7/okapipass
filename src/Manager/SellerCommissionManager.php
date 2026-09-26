<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateSellerCommissionRuleDto;
use App\Dto\Agency\UpdateSellerCommissionRuleDto;
use App\Entity\Agency;
use App\Entity\PosSession;
use App\Entity\SellerBonusLedger;
use App\Entity\SellerCommissionRule;
use App\Entity\User;
use App\Repository\AgencyPaymentRepository;
use App\Repository\SellerBonusLedgerRepository;
use App\Repository\SellerCommissionRuleRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class SellerCommissionManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private SellerCommissionRuleRepository $rules,
        private SellerBonusLedgerRepository $ledgers,
        private AgencyPaymentRepository $payments,
    ) {
    }

    public function createRule(CreateSellerCommissionRuleDto $dto): SellerCommissionRule
    {
        $this->agencyContext->requirePermission(AgencyPermission::POS_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $rule = new SellerCommissionRule();
        $rule->setAgency($agency);
        $rule->setPeriodType((string) $dto->periodType);
        $rule->setTargetTickets((int) ($dto->targetTickets ?? 0));
        $rule->setTargetRevenue((int) ($dto->targetRevenue ?? 0));
        $rule->setBonusType((string) $dto->bonusType);
        $rule->setBonusValue((int) ($dto->bonusValue ?? 0));
        $rule->setActive(false !== $dto->active);

        $this->em->persist($rule);
        $this->em->flush();

        return $rule;
    }

    public function updateRule(SellerCommissionRule $rule, UpdateSellerCommissionRuleDto $dto): SellerCommissionRule
    {
        $this->agencyContext->requirePermission(AgencyPermission::POS_WRITE);
        $this->agencyContext->assertOwns($rule->getAgency());

        if (null !== $dto->periodType) {
            $rule->setPeriodType($dto->periodType);
        }
        if (null !== $dto->targetTickets) {
            $rule->setTargetTickets($dto->targetTickets);
        }
        if (null !== $dto->targetRevenue) {
            $rule->setTargetRevenue($dto->targetRevenue);
        }
        if (null !== $dto->bonusType) {
            $rule->setBonusType($dto->bonusType);
        }
        if (null !== $dto->bonusValue) {
            $rule->setBonusValue($dto->bonusValue);
        }
        if (null !== $dto->active) {
            $rule->setActive($dto->active);
        }

        $this->em->flush();

        return $rule;
    }

    public function accrueForSession(PosSession $session): void
    {
        $agency = $session->getAgency();
        $seller = $session->getSeller();
        if (!$agency instanceof Agency || !$seller instanceof User) {
            return;
        }

        $anchor = $session->getClosedAt() ?? $session->getOpenedAt() ?? new \DateTimeImmutable();
        foreach ($this->rules->findActiveForAgency($agency) as $rule) {
            [$periodStart, $periodEnd] = $this->resolvePeriod($rule->getPeriodType(), $anchor);
            if ($this->ledgers->findOneForSellerPeriodRule($agency, $seller, $periodStart, $periodEnd, $rule) instanceof SellerBonusLedger) {
                continue;
            }

            [$tickets, $revenue] = $this->sellerStats($agency, $seller, $periodStart, $periodEnd);
            if ($tickets < $rule->getTargetTickets() || $revenue < $rule->getTargetRevenue()) {
                continue;
            }

            $amount = match ($rule->getBonusType()) {
                SellerCommissionRule::BONUS_PERCENT => (int) floor($revenue * $rule->getBonusValue() / 100),
                default => $rule->getBonusValue(),
            };
            if ($amount <= 0) {
                continue;
            }

            $ledger = new SellerBonusLedger();
            $ledger->setAgency($agency);
            $ledger->setSeller($seller);
            $ledger->setAmount($amount);
            $ledger->setCurrency($agency->getDefaultCurrency());
            $ledger->setPeriodStart($periodStart);
            $ledger->setPeriodEnd($periodEnd);
            $ledger->setRule($rule);
            $ledger->setStatus(SellerBonusLedger::STATUS_ACCRUED);
            $this->em->persist($ledger);
        }

        $this->em->flush();
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function resolvePeriod(string $periodType, \DateTimeImmutable $anchor): array
    {
        if (SellerCommissionRule::PERIOD_MONTH === $periodType) {
            $start = $anchor->modify('first day of this month')->setTime(0, 0);
            $end = $anchor->modify('last day of this month')->setTime(0, 0);

            return [$start, $end];
        }

        $day = $anchor->setTime(0, 0);

        return [$day, $day];
    }

    /** @return array{0: int, 1: int} */
    private function sellerStats(Agency $agency, User $seller, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $payments = $this->payments->findPaidPosForAgencyBetween(
            $agency,
            $from->setTime(0, 0),
            $to->setTime(23, 59, 59),
        );

        $tickets = 0;
        $revenue = 0;
        $sellerId = (string) $seller->getId();
        foreach ($payments as $payment) {
            $notes = (string) $payment->getNotes();
            if (!preg_match('/POS session (PS\w+)/', $notes, $m)) {
                continue;
            }
            $session = $this->em->getRepository(PosSession::class)->find($m[1]);
            if (!$session instanceof PosSession || $session->getSeller()?->getId() !== $sellerId) {
                continue;
            }
            $revenue += $payment->getAmount();
            if (null !== $payment->getTicket()) {
                ++$tickets;
            }
        }

        return [$tickets, $revenue];
    }
}
