<?php

namespace App\Dto\Agency;

use App\Entity\Promotion;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdatePromotionDto
{
    public function __construct(
        #[Assert\Length(max: 40)]
        public ?string $code = null,

        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\Choice(callback: [Promotion::class, 'getDiscountTypesAsList'])]
        public ?string $discountType = null,

        #[Assert\PositiveOrZero]
        public ?int $discountValue = null,

        #[Assert\Positive]
        public ?int $maxUses = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $validFrom = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $validTo = null,

        public ?bool $active = null,

        #[Assert\PositiveOrZero]
        public ?int $maxDiscountAmount = null,

        /** @var list<int>|null */
        public ?array $excludedWeekdays = null,

        #[Assert\Positive]
        public ?int $maxUsesPerPhone = null,

        /** @var list<string>|null */
        public ?array $excludedSeatClasses = null,
    ) {
    }
}
