<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyBookingDto
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public ?string $passengerName = null,

        #[Assert\Length(max: 60)]
        public ?string $passengerId = null,

        #[Assert\Length(max: 20)]
        public ?string $passengerPhone = null,

        #[Assert\Length(max: 10)]
        public ?string $seatNumber = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $travelDate = null,

        #[Assert\Length(max: 40)]
        public ?string $okapiPassRef = null,
    ) {
    }
}
