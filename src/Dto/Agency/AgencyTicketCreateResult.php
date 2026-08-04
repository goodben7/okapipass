<?php

namespace App\Dto\Agency;

use App\Entity\AgencyTicket;
use Symfony\Component\Serializer\Attribute\Groups;

class AgencyTicketCreateResult
{
    public function __construct(
        #[Groups(['agency_ticket:get'])]
        public AgencyTicket $ticket,
        #[Groups(['agency_ticket:get'])]
        public ?string $smsMessageId = null,
    ) {
    }
}
