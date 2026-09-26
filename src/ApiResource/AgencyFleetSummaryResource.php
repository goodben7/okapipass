<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFleetSummaryProvider;

#[ApiResource(
    shortName: 'AgencyFleetSummary',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/reports/summary',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyFleetSummaryProvider::class,
        ),
    ]
)]
final class AgencyFleetSummaryResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $from,
        public string $to,
        public float $availabilityPercent = 0,
        public ?float $occupancyPercent = null,
        public int $incidentCount = 0,
        public int $maintenanceCost = 0,
    ) {
    }
}
