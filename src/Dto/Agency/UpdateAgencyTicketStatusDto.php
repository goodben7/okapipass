<?php

namespace App\Dto\Agency;

use App\Entity\AgencyTicket;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyTicketStatusDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [AgencyTicket::class, 'getStatusesAsList'])]
        public ?string $status = null,
    ) {
    }
}
