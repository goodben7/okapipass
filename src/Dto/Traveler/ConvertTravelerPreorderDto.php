<?php

namespace App\Dto\Traveler;

final class ConvertTravelerPreorderDto
{
    public function __construct(
        public ?string $seatNumber = null,
        public ?string $travelDate = null,
    ) {
    }
}
