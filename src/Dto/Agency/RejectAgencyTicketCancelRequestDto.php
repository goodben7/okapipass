<?php

namespace App\Dto\Agency;

final class RejectAgencyTicketCancelRequestDto
{
    public function __construct(
        public ?string $notes = null,
    ) {
    }
}
