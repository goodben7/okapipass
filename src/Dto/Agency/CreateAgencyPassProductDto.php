<?php

namespace App\Dto\Agency;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyPassProductDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 40)]
        public ?string $code = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\Length(max: 120)]
        public ?string $origin = null,

        #[Assert\Length(max: 120)]
        public ?string $destination = null,

        #[Assert\PositiveOrZero]
        public ?int $tripsAllowed = null,

        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $validityDays = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $price = null,

        #[Assert\Length(max: 3)]
        public ?string $currency = Agency::DEFAULT_CURRENCY,

        public ?bool $active = true,

        #[Assert\Choice(choices: [
            AgencyOffer::SERVICE_INTERCITY,
            AgencyOffer::SERVICE_URBAN,
        ])]
        public ?string $serviceType = null,
    ) {
    }
}
