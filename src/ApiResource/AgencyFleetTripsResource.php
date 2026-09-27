<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFleetTripsProvider;

/**
 * GET /api/agency/fleet/trips?date=&serviceType=&unassignedOnly=
 * Lists courses (embarkations) for fleet planning UI.
 */
#[ApiResource(
    shortName: 'AgencyFleetTrips',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/trips',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyFleetTripsProvider::class,
        ),
    ]
)]
final class AgencyFleetTripsResource
{
    /**
     * @param list<array<string, mixed>> $trips
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public array $trips,
    ) {
    }
}
