<?php

namespace App\Domain\Agency;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use App\Entity\LoyaltyRule;
use App\Entity\Promotion;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyTicketRepository;
use App\Repository\LoyaltyRuleRepository;
use App\Repository\PromotionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyDiscountService
{
    public function __construct(
        private PromotionRepository $promotions,
        private LoyaltyRuleRepository $loyaltyRules,
        private AgencyTicketRepository $tickets,
        private EntityManagerInterface $em,
        private SeatLayoutBuilder $seatLayout,
    ) {
    }

    /**
     * @return array{discountAmount: int, promoCode: string, promotionId: string}
     */
    public function validatePromo(
        Agency $agency,
        string $code,
        int $ticketPrice,
        ?string $phone = null,
        ?string $seatNumber = null,
    ): array {
        $promotion = $this->promotions->findActiveByCode($agency, $code);
        if (!$promotion instanceof Promotion) {
            throw new UnavailableDataException('Promotion code not found.');
        }

        $this->assertPromoUsable($promotion, $phone, $seatNumber);

        $discount = $this->computeDiscountAmount(
            $promotion->getDiscountType(),
            $promotion->getDiscountValue(),
            $ticketPrice,
            $promotion->getMaxDiscountAmount(),
        );

        return [
            'discountAmount' => $discount,
            'promoCode' => (string) $promotion->getCode(),
            'promotionId' => (string) $promotion->getId(),
        ];
    }

    public function applyPromo(Agency $agency, string $code): Promotion
    {
        $promotion = $this->promotions->findActiveByCode($agency, $code);
        if (!$promotion instanceof Promotion) {
            throw new UnavailableDataException('Promotion code not found.');
        }

        $this->assertPromoUsable($promotion);
        $promotion->setUsedCount($promotion->getUsedCount() + 1);
        $this->em->flush();

        return $promotion;
    }

    /**
     * Promo code wins when provided; otherwise best non-stackable loyalty reward.
     *
     * @return array{discountAmount: int, promoCode: ?string, promotionId: ?string, loyaltyRuleId: ?string}
     */
    public function resolveDiscount(
        Agency $agency,
        int $ticketPrice,
        ?string $promoCode = null,
        ?string $phone = null,
        ?AgencyOffer $offer = null,
        ?string $seatNumber = null,
    ): array {
        $code = null !== $promoCode ? trim($promoCode) : '';
        if ('' !== $code) {
            $promo = $this->validatePromo($agency, $code, $ticketPrice, $phone, $seatNumber);

            return [
                'discountAmount' => $promo['discountAmount'],
                'promoCode' => $promo['promoCode'],
                'promotionId' => $promo['promotionId'],
                'loyaltyRuleId' => null,
            ];
        }

        $loyalty = $this->resolveLoyaltyDiscount($agency, $ticketPrice, $phone, $offer, $seatNumber);

        return [
            'discountAmount' => $loyalty['discountAmount'],
            'promoCode' => null,
            'promotionId' => null,
            'loyaltyRuleId' => $loyalty['loyaltyRuleId'],
        ];
    }

    /**
     * @return array{discountAmount: int, loyaltyRuleId: ?string}
     */
    public function resolveLoyaltyDiscount(
        Agency $agency,
        int $ticketPrice,
        ?string $phone = null,
        ?AgencyOffer $offer = null,
        ?string $seatNumber = null,
    ): array {
        $phone = null !== $phone ? trim($phone) : '';
        if ('' === $phone || $ticketPrice <= 0) {
            return ['discountAmount' => 0, 'loyaltyRuleId' => null];
        }

        $tripCount = $this->tickets->countByPassengerPhone($agency, $phone);
        $bestAmount = 0;
        $bestRuleId = null;
        $weekday = (int) (new \DateTimeImmutable('today'))->format('w');
        $seatClass = null !== $seatNumber && '' !== trim($seatNumber)
            ? $this->seatLayout->seatClassFor($seatNumber)
            : null;

        foreach ($this->loyaltyRules->findActiveForAgency($agency) as $rule) {
            if (!$this->ruleMatchesOffer($rule, $offer)) {
                continue;
            }
            if ($this->isWeekdayExcluded($rule->getExcludedWeekdays(), $weekday)) {
                continue;
            }
            if (null !== $seatClass && $this->isSeatClassExcluded($rule->getExcludedSeatClasses(), $seatClass)) {
                continue;
            }
            if (!$this->ruleTriggered($rule, $tripCount)) {
                continue;
            }

            $amount = $this->computeDiscountAmount(
                $rule->getRewardType(),
                $rule->getRewardValue(),
                $ticketPrice,
                $rule->getMaxDiscountAmount(),
            );
            if ($amount > $bestAmount) {
                $bestAmount = $amount;
                $bestRuleId = $rule->getId();
            }
        }

        return [
            'discountAmount' => $bestAmount,
            'loyaltyRuleId' => $bestRuleId,
        ];
    }

    public function assertPromoUsable(Promotion $promotion, ?string $phone = null, ?string $seatNumber = null): void
    {
        $today = new \DateTimeImmutable('today');
        $from = $promotion->getValidFrom();
        $to = $promotion->getValidTo();

        if (null !== $from && $today < $from) {
            throw new UnprocessableEntityException('Promotion is not yet valid.');
        }
        if (null !== $to && $today > $to) {
            throw new UnprocessableEntityException('Promotion has expired.');
        }

        $maxUses = $promotion->getMaxUses();
        if (null !== $maxUses && $promotion->getUsedCount() >= $maxUses) {
            throw new UnprocessableEntityException('Promotion usage limit reached.');
        }

        $weekday = (int) $today->format('w');
        if ($this->isWeekdayExcluded($promotion->getExcludedWeekdays(), $weekday)) {
            throw new UnprocessableEntityException('Promotion is not valid on this weekday.');
        }

        if (null !== $seatNumber && '' !== trim($seatNumber)) {
            $seatClass = $this->seatLayout->seatClassFor($seatNumber);
            if ($this->isSeatClassExcluded($promotion->getExcludedSeatClasses(), $seatClass)) {
                throw new UnprocessableEntityException(sprintf(
                    'Promotion is not valid for seat class %s.',
                    $seatClass,
                ));
            }
        }

        $maxPerPhone = $promotion->getMaxUsesPerPhone();
        $phone = null !== $phone ? trim($phone) : '';
        if (null !== $maxPerPhone && '' !== $phone && null !== $promotion->getAgency()) {
            $used = $this->tickets->countByPassengerPhoneAndPromoCode(
                $promotion->getAgency(),
                $phone,
                (string) $promotion->getCode(),
            );
            if ($used >= $maxPerPhone) {
                throw new UnprocessableEntityException('Promotion usage limit reached for this phone.');
            }
        }
    }

    private function ruleMatchesOffer(LoyaltyRule $rule, ?AgencyOffer $offer): bool
    {
        $ruleOffer = $rule->getOffer();
        if (null !== $ruleOffer) {
            return null !== $offer && $ruleOffer->getId() === $offer->getId();
        }

        if (null === $offer) {
            return null === $rule->getOrigin() && null === $rule->getDestination();
        }

        if (null !== $rule->getOrigin() && $rule->getOrigin() !== $offer->getOrigin()) {
            return false;
        }
        if (null !== $rule->getDestination() && $rule->getDestination() !== $offer->getDestination()) {
            return false;
        }

        return true;
    }

    private function ruleTriggered(LoyaltyRule $rule, int $tripCount): bool
    {
        return match ($rule->getTriggerType()) {
            LoyaltyRule::TRIGGER_FIRST_PURCHASE => 0 === $tripCount,
            LoyaltyRule::TRIGGER_TRIP_COUNT => $tripCount >= $rule->getThreshold(),
            default => false,
        };
    }

    private function computeDiscountAmount(string $type, int $value, int $ticketPrice, ?int $maxDiscountAmount = null): int
    {
        $amount = match ($type) {
            Promotion::DISCOUNT_PERCENT_OFF, LoyaltyRule::REWARD_PERCENT_OFF => (int) floor($ticketPrice * $value / 100),
            Promotion::DISCOUNT_FIXED_OFF, LoyaltyRule::REWARD_FIXED_OFF => $value,
            default => 0,
        };

        $amount = max(0, min($amount, $ticketPrice));
        if (null !== $maxDiscountAmount && $maxDiscountAmount >= 0) {
            $amount = min($amount, $maxDiscountAmount);
        }

        return $amount;
    }

    /** @param list<int>|null $excluded */
    private function isWeekdayExcluded(?array $excluded, int $weekday): bool
    {
        if (null === $excluded || [] === $excluded) {
            return false;
        }

        return \in_array($weekday, $excluded, true);
    }

    /** @param list<string>|null $excluded */
    private function isSeatClassExcluded(?array $excluded, string $seatClass): bool
    {
        if (null === $excluded || [] === $excluded) {
            return false;
        }

        return \in_array(strtoupper($seatClass), $excluded, true);
    }
}
