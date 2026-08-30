<?php

namespace App\Dto\Public;

use Symfony\Component\Validator\Constraints as Assert;

final class CreatePublicAgencyBookingDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $offerId = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $travelDate = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        public ?string $seatNumber = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $passengerName = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 60)]
        public ?string $passengerId = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public ?string $passengerPhone = null,

        #[Assert\Length(max: 40)]
        public ?string $okapiPassRef = null,
    ) {
    }
}
