<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyTransportAvailabilityProvider;

/**
 * GET /api/agency/transports/{transportId}/availability?from=&to= (fleet F3).
 */
#[ApiResource(
    shortName: 'AgencyTransportAvailability',
    operations: [
        new Get(
            uriTemplate: '/agency/transports/{transportId}/availability',
            uriVariables: ['transportId'],
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyTransportAvailabilityProvider::class,
        ),
    ]
)]
class AgencyTransportAvailabilityResource
{
    /**
     * @param list<array{date: string, available: bool, reason: string|null}> $days
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $transportId,
        public string $from,
        public string $to,
        public array $days,
    ) {
    }
}
