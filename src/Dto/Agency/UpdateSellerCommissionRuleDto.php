<?php

namespace App\Dto\Agency;

use App\Entity\SellerCommissionRule;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateSellerCommissionRuleDto
{
    public function __construct(
        #[Assert\Choice(callback: [SellerCommissionRule::class, 'getPeriodTypesAsList'])]
        public ?string $periodType = null,

        #[Assert\PositiveOrZero]
        public ?int $targetTickets = null,

        #[Assert\PositiveOrZero]
        public ?int $targetRevenue = null,

        #[Assert\Choice(callback: [SellerCommissionRule::class, 'getBonusTypesAsList'])]
        public ?string $bonusType = null,

        #[Assert\PositiveOrZero]
        public ?int $bonusValue = null,

        public ?bool $active = null,
    ) {
    }
}
