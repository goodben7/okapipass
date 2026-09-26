<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyPassProductDto
{
    public function __construct(
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\Length(max: 120)]
        public ?string $origin = null,

        #[Assert\Length(max: 120)]
        public ?string $destination = null,

        #[Assert\PositiveOrZero]
        public ?int $tripsAllowed = null,

        #[Assert\Positive]
        public ?int $validityDays = null,

        #[Assert\PositiveOrZero]
        public ?int $price = null,

        #[Assert\Length(max: 3)]
        public ?string $currency = null,

        public ?bool $active = null,
    ) {
    }
}
