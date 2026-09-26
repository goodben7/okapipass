<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFleetPunctualityProvider;

#[ApiResource(
    shortName: 'AgencyFleetPunctuality',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/reports/punctuality',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyFleetPunctualityProvider::class,
        ),
    ]
)]
final class AgencyFleetPunctualityResource
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $from,
        public string $to,
        public array $rows = [],
    ) {
    }
}
