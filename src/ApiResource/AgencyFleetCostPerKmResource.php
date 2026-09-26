<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFleetCostPerKmProvider;

#[ApiResource(
    shortName: 'AgencyFleetCostPerKm',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/reports/cost-per-km',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyFleetCostPerKmProvider::class,
        ),
    ]
)]
final class AgencyFleetCostPerKmResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $transportId,
        public string $from,
        public string $to,
        public int $fuelCost = 0,
        public int $maintenanceCost = 0,
        public int $deltaOdometer = 0,
        public ?float $costPerKm = null,
        public float $litersPer100Km = 0.0,
    ) {
    }
}
