<?php

namespace App\Dto\Agency;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

final class ValidateBoardingResultDto
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['validate_boarding:get'])]
        public string $ticketId,
        #[Groups(['validate_boarding:get'])]
        public string $status,
        #[Groups(['validate_boarding:get'])]
        public int $boardedCount,
        #[Groups(['validate_boarding:get'])]
        public ?string $seatNumber = null,
        #[Groups(['validate_boarding:get'])]
        public ?string $passengerName = null,
        #[Groups(['validate_boarding:get'])]
        public ?string $reference = null,
        #[Groups(['validate_boarding:get'])]
        public ?string $embarkationId = null,
        #[Groups(['validate_boarding:get'])]
        public bool $geofenceWarning = false,
        #[Groups(['validate_boarding:get'])]
        public array $warnings = [],
    ) {
    }
}
