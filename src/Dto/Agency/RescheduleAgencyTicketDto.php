<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class RescheduleAgencyTicketDto
{
    public function __construct(
        public ?string $offerId = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $travelDate = null,

        #[Assert\Length(max: 10)]
        public ?string $seatNumber = null,
    ) {
    }
}
