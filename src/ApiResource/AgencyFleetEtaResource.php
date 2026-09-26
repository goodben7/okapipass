<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFleetEtaProvider;

#[ApiResource(
    shortName: 'AgencyFleetEta',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/departures/{embarkationId}/eta',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyFleetEtaProvider::class,
        ),
    ]
)]
final class AgencyFleetEtaResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public int $etaMinutes = 15,
        public ?float $lat = null,
        public ?float $lng = null,
        public string $status = 'STUB',
    ) {
    }
}
