<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyDriverMissionsProvider;

#[ApiResource(
    shortName: 'AgencyDriverMissions',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/driver-missions',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyDriverMissionsProvider::class,
        ),
    ]
)]
final class AgencyDriverMissionsResource
{
    /** @param list<array<string, mixed>> $missions */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $date,
        public ?string $driver = null,
        public array $missions = [],
    ) {
    }
}
