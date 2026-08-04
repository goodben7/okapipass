<?php

namespace App\Dto\Agency;

use App\Entity\AgencyBooking;
use Symfony\Component\Serializer\Attribute\Groups;

class AgencyBookingCreateResult
{
    public function __construct(
        #[Groups(['agency_booking:get'])]
        public AgencyBooking $booking,
        #[Groups(['agency_booking:get'])]
        public ?string $smsMessageId = null,
    ) {
    }
}
