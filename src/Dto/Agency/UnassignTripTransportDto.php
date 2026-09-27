<?php

namespace App\Dto\Agency;

final class UnassignTripTransportDto
{
    public function __construct(
        public bool $force = false,
        public ?string $reason = null,
    ) {
    }
}
