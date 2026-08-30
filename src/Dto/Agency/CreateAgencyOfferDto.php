<?php

namespace App\Dto\Agency;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use Symfony\Component\Validator\Constraints as Assert;

class CreateAgencyOfferDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $origin = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $destination = null,

        /** IRI or id of AgencyTransport */
        #[Assert\NotBlank]
        public ?string $transport = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $ticketPrice = null,

        #[Assert\Currency]
        public ?string $currency = Agency::DEFAULT_CURRENCY,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/')]
        public ?string $departureTime = null,

        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $durationMinutes = null,

        public ?bool $active = true,

        public ?bool $onlineSales = false,

        #[Assert\Positive]
        public ?int $bookingHoldMinutes = AgencyOffer::DEFAULT_BOOKING_HOLD_MINUTES,
    ) {
    }
}
