<?php

namespace App\Dto\Agency;

use App\Entity\SellerCommissionRule;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateSellerCommissionRuleDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [SellerCommissionRule::class, 'getPeriodTypesAsList'])]
        public ?string $periodType = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $targetTickets = 0,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $targetRevenue = 0,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [SellerCommissionRule::class, 'getBonusTypesAsList'])]
        public ?string $bonusType = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $bonusValue = 0,

        public ?bool $active = true,
    ) {
    }
}
