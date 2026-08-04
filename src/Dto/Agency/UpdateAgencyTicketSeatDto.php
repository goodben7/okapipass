<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyTicketSeatDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        public ?string $seatNumber = null,
    ) {
    }
}
