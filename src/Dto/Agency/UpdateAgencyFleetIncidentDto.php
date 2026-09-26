<?php

namespace App\Dto\Agency;

final class UpdateAgencyFleetIncidentDto
{
    public function __construct(
        public ?string $type = null,
        public ?string $severity = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $photoUrl = null,
        public ?string $notes = null,
        public ?string $occurredAt = null,
    ) {
    }
}
