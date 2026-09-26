<?php

namespace App\Dto\Agency;

use App\Entity\LoyaltyRule;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateLoyaltyRuleDto
{
    public function __construct(
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\Choice(callback: [LoyaltyRule::class, 'getTriggerTypesAsList'])]
        public ?string $triggerType = null,

        #[Assert\Choice(callback: [LoyaltyRule::class, 'getWindowsAsList'])]
        public ?string $window = null,

        #[Assert\PositiveOrZero]
        public ?int $threshold = null,

        #[Assert\Choice(callback: [LoyaltyRule::class, 'getRewardTypesAsList'])]
        public ?string $rewardType = null,

        #[Assert\PositiveOrZero]
        public ?int $rewardValue = null,

        #[Assert\Length(max: 120)]
        public ?string $origin = null,

        #[Assert\Length(max: 120)]
        public ?string $destination = null,

        public ?string $offer = null,

        public ?string $surprisePool = null,

        #[Assert\PositiveOrZero]
        public ?int $pointsEarn = null,

        public ?bool $stackable = null,

        public ?bool $active = null,

        #[Assert\PositiveOrZero]
        public ?int $maxDiscountAmount = null,

        /** @var list<int>|null */
        public ?array $excludedWeekdays = null,

        /** @var list<string>|null */
        public ?array $excludedSeatClasses = null,
    ) {
    }
}
