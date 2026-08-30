<?php

namespace App\ApiResource\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\PublicAgency\PublicAgencySeatAvailabilityProvider;

#[ApiResource(
    shortName: 'PublicAgencySeatAvailability',
    operations: [
        new Get(
            uriTemplate: '/public/agency/offers/{offerId}/seats',
            uriVariables: ['offerId'],
            provider: PublicAgencySeatAvailabilityProvider::class,
        ),
    ]
)]
final class PublicAgencySeatAvailabilityResource
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
