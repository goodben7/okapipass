<?php

namespace App\Dto\Agency;

use App\Entity\SurprisePoolItem;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateSurprisePoolItemDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [SurprisePoolItem::class, 'getRewardTypesAsList'])]
        public ?string $rewardType = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $rewardValue = null,

        #[Assert\Positive]
        public ?int $weight = 1,

        public ?bool $active = true,
    ) {
    }
}
