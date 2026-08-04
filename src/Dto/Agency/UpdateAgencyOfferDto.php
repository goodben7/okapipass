<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyOfferDto
{
    public function __construct(
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\Length(max: 120)]
        public ?string $origin = null,

        #[Assert\Length(max: 120)]
        public ?string $destination = null,

        public ?string $transport = null,

        #[Assert\PositiveOrZero]
        public ?int $ticketPrice = null,

        #[Assert\Currency]
        public ?string $currency = null,

        #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/')]
        public ?string $departureTime = null,

        #[Assert\Positive]
        public ?int $durationMinutes = null,

        public ?bool $active = null,
    ) {
    }
}
