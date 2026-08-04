<?php

namespace App\Dto\Agency;

use App\Entity\AgencyBooking;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyBookingStatusDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: [
            AgencyBooking::STATUS_CONFIRMED,
            AgencyBooking::STATUS_CANCELLED,
        ])]
        public ?string $status = null,
    ) {
    }
}
