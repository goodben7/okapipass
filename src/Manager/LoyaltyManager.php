<?php

namespace App\Manager;

use App\Domain\Agency\AgencyDiscountService;
use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateLoyaltyRuleDto;
use App\Dto\Agency\CreatePromotionDto;
use App\Dto\Agency\UpdateLoyaltyRuleDto;
use App\Dto\Agency\UpdatePromotionDto;
use App\Dto\Agency\ValidatePromotionDto;
use App\Entity\AgencyOffer;
use App\Entity\LoyaltyRule;
use App\Entity\Promotion;
use App\Entity\SurprisePool;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyOfferRepository;
use App\Repository\LoyaltyRuleRepository;
use App\Repository\PromotionRepository;
use App\Repository\SurprisePoolRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class LoyaltyManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private LoyaltyRuleRepository $rules,
        private PromotionRepository $promotions,
        private AgencyOfferRepository $offers,
        private SurprisePoolRepository $surprisePools,
        private AgencyDiscountService $discounts,
    ) {
    }

    public function createRule(CreateLoyaltyRuleDto $dto): LoyaltyRule
    {
        $this->agencyContext->requirePermission(AgencyPermission::LOYALTY_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $rule = new LoyaltyRule();
        $rule->setAgency($agency);
        $rule->setLabel((string) $dto->label);
        $rule->setTriggerType((string) $dto->triggerType);
        $rule->setWindow((string) $dto->window);
        $rule->setThreshold((int) $dto->threshold);
        $rule->setRewardType((string) $dto->rewardType);
        $rule->setRewardValue((int) $dto->rewardValue);
        $rule->setOrigin($dto->origin);
        $rule->setDestination($dto->destination);
        $rule->setOffer($this->resolveOfferOptional($dto->offer, $agency->getId()));
        $rule->setSurprisePool($this->resolveSurprisePoolOptional($dto->surprisePool, $agency->getId()));
        $rule->setPointsEarn((int) ($dto->pointsEarn ?? 0));
        $rule->setStackable((bool) ($dto->stackable ?? false));
        $rule->setActive(false !== $dto->active);
        $rule->setMaxDiscountAmount($dto->maxDiscountAmount);
        $rule->setExcludedWeekdays($dto->excludedWeekdays);
        $rule->setExcludedSeatClasses($dto->excludedSeatClasses);

        $this->em->persist($rule);
        $this->em->flush();

        return $rule;
    }

    public function updateRule(LoyaltyRule $rule, UpdateLoyaltyRuleDto $dto): LoyaltyRule
    {
        $this->agencyContext->requirePermission(AgencyPermission::LOYALTY_WRITE);
        $this->agencyContext->assertOwns($rule->getAgency());

        if (null !== $dto->label) {
            $rule->setLabel($dto->label);
        }
        if (null !== $dto->triggerType) {
            $rule->setTriggerType($dto->triggerType);
        }
        if (null !== $dto->window) {
            $rule->setWindow($dto->window);
        }
        if (null !== $dto->threshold) {
            $rule->setThreshold($dto->threshold);
        }
        if (null !== $dto->rewardType) {
            $rule->setRewardType($dto->rewardType);
        }
        if (null !== $dto->rewardValue) {
            $rule->setRewardValue($dto->rewardValue);
        }
        if (null !== $dto->origin) {
            $rule->setOrigin('' === $dto->origin ? null : $dto->origin);
        }
        if (null !== $dto->destination) {
            $rule->setDestination('' === $dto->destination ? null : $dto->destination);
        }
        if (null !== $dto->offer) {
            $rule->setOffer('' === trim($dto->offer)
                ? null
                : $this->resolveOfferOptional($dto->offer, $rule->getAgency()?->getId()));
        }
        if (null !== $dto->surprisePool) {
            $rule->setSurprisePool('' === trim($dto->surprisePool)
                ? null
                : $this->resolveSurprisePoolOptional($dto->surprisePool, $rule->getAgency()?->getId()));
        }
        if (null !== $dto->pointsEarn) {
            $rule->setPointsEarn($dto->pointsEarn);
        }
        if (null !== $dto->stackable) {
            $rule->setStackable($dto->stackable);
        }
        if (null !== $dto->active) {
            $rule->setActive($dto->active);
        }
        if (null !== $dto->maxDiscountAmount) {
            $rule->setMaxDiscountAmount($dto->maxDiscountAmount);
        }
        if (null !== $dto->excludedWeekdays) {
            $rule->setExcludedWeekdays($dto->excludedWeekdays);
        }
        if (null !== $dto->excludedSeatClasses) {
            $rule->setExcludedSeatClasses($dto->excludedSeatClasses);
        }

        $this->em->flush();

        return $rule;
    }

    public function createPromotion(CreatePromotionDto $dto): Promotion
    {
        $this->agencyContext->requirePermission(AgencyPermission::LOYALTY_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $existing = $this->promotions->findOneBy([
            'agency' => $agency,
            'code' => strtoupper(trim((string) $dto->code)),
        ]);
        if ($existing instanceof Promotion) {
            throw new UnprocessableEntityException('A promotion with this code already exists.');
        }

        $promotion = new Promotion();
        $promotion->setAgency($agency);
        $promotion->setCode((string) $dto->code);
        $promotion->setLabel((string) $dto->label);
        $promotion->setDiscountType((string) $dto->discountType);
        $promotion->setDiscountValue((int) $dto->discountValue);
        $promotion->setMaxUses($dto->maxUses);
        $promotion->setValidFrom(null !== $dto->validFrom ? $this->parseDate($dto->validFrom) : null);
        $promotion->setValidTo(null !== $dto->validTo ? $this->parseDate($dto->validTo) : null);
        $promotion->setActive(false !== $dto->active);
        $promotion->setMaxDiscountAmount($dto->maxDiscountAmount);
        $promotion->setExcludedWeekdays($dto->excludedWeekdays);
        $promotion->setMaxUsesPerPhone($dto->maxUsesPerPhone);
        $promotion->setExcludedSeatClasses($dto->excludedSeatClasses);

        $this->em->persist($promotion);
        $this->em->flush();

        return $promotion;
    }

    public function updatePromotion(Promotion $promotion, UpdatePromotionDto $dto): Promotion
    {
        $this->agencyContext->requirePermission(AgencyPermission::LOYALTY_WRITE);
        $this->agencyContext->assertOwns($promotion->getAgency());

        if (null !== $dto->code) {
            $code = strtoupper(trim($dto->code));
            $existing = $this->promotions->findOneBy([
                'agency' => $promotion->getAgency(),
                'code' => $code,
            ]);
            if ($existing instanceof Promotion && $existing->getId() !== $promotion->getId()) {
                throw new UnprocessableEntityException('A promotion with this code already exists.');
            }
            $promotion->setCode($code);
        }
        if (null !== $dto->label) {
            $promotion->setLabel($dto->label);
        }
        if (null !== $dto->discountType) {
            $promotion->setDiscountType($dto->discountType);
        }
        if (null !== $dto->discountValue) {
            $promotion->setDiscountValue($dto->discountValue);
        }
        if (null !== $dto->maxUses) {
            $promotion->setMaxUses($dto->maxUses);
        }
        if (null !== $dto->validFrom) {
            $promotion->setValidFrom('' === $dto->validFrom ? null : $this->parseDate($dto->validFrom));
        }
        if (null !== $dto->validTo) {
            $promotion->setValidTo('' === $dto->validTo ? null : $this->parseDate($dto->validTo));
        }
        if (null !== $dto->active) {
            $promotion->setActive($dto->active);
        }
        if (null !== $dto->maxDiscountAmount) {
            $promotion->setMaxDiscountAmount($dto->maxDiscountAmount);
        }
        if (null !== $dto->excludedWeekdays) {
            $promotion->setExcludedWeekdays($dto->excludedWeekdays);
        }
        if (null !== $dto->maxUsesPerPhone) {
            $promotion->setMaxUsesPerPhone($dto->maxUsesPerPhone);
        }
        if (null !== $dto->excludedSeatClasses) {
            $promotion->setExcludedSeatClasses($dto->excludedSeatClasses);
        }

        $this->em->flush();

        return $promotion;
    }

    /**
     * @return array{discountAmount: int, promoCode: ?string, promotionId: ?string, loyaltyRuleId: ?string, finalTicketPrice: int}
     */
    public function validatePromotion(ValidatePromotionDto $dto): array
    {
        $agency = $this->agencyContext->requireAgency();
        $offer = $this->resolveOfferOptional($dto->offer, $agency->getId());
        $ticketPrice = $dto->ticketPrice;
        if (null === $ticketPrice) {
            $ticketPrice = $offer?->getTicketPrice() ?? 0;
        }

        $resolved = $this->discounts->resolveDiscount(
            $agency,
            (int) $ticketPrice,
            $dto->code,
            $dto->phone,
            $offer,
        );

        return [
            'discountAmount' => $resolved['discountAmount'],
            'promoCode' => $resolved['promoCode'],
            'promotionId' => $resolved['promotionId'],
            'loyaltyRuleId' => $resolved['loyaltyRuleId'],
            'finalTicketPrice' => max(0, (int) $ticketPrice - $resolved['discountAmount']),
        ];
    }

    private function resolveSurprisePoolOptional(?string $ref, ?string $agencyId): ?SurprisePool
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $id = $this->extractId($ref);
        $pool = $this->surprisePools->find($id);
        if (!$pool instanceof SurprisePool || $pool->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('Surprise pool "%s" not found.', $id));
        }

        return $pool;
    }

    private function resolveOfferOptional(?string $ref, ?string $agencyId): ?AgencyOffer
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $id = $this->extractId($ref);
        $offer = $this->offers->find($id);
        if (!$offer instanceof AgencyOffer || $offer->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('Offer "%s" not found.', $id));
        }

        return $offer;
    }

    private function extractId(string $ref): string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));

            return (string) end($parts);
        }

        return $ref;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $d) {
            throw new UnprocessableEntityException('Invalid date, expected YYYY-MM-DD.');
        }

        return $d->setTime(0, 0);
    }
}
