<?php

namespace App\ApiResource\Public;

final class PublicAgencyBookingGroupTicketsResource
{
    public function __construct(
        public string $publicToken,
        public string $groupId,
        public string $groupName,
        public PublicAgencyTicketResource $ticket,
    ) {
    }
}
