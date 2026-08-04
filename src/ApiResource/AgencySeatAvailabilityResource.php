<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\SeatAvailabilityProvider;

/**
 * GET /api/agency/offers/{offerId}/seat-availability (spec §6.4).
 */
#[ApiResource(
    shortName: 'AgencySeatAvailability',
    operations: [
        new Get(
            uriTemplate: '/agency/offers/{offerId}/seat-availability',
            uriVariables: ['offerId'],
            security: 'is_granted("ROLE_PARTNER")',
            provider: SeatAvailabilityProvider::class,
        ),
    ]
)]
class AgencySeatAvailabilityResource
{
    /**
     * @param array<string, mixed> $layout
     * @param list<string>         $occupiedSeats
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $offerId,
        public string $travelDate,
        public int $capacity,
        public int $availableCount,
        public bool $isFull,
        public array $layout,
        public array $occupiedSeats,
    ) {
    }
}
