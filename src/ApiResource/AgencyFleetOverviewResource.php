<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFleetOverviewProvider;

/**
 * GET /api/agency/fleet/overview (fleet F4 hub KPIs).
 */
#[ApiResource(
    shortName: 'AgencyFleetOverview',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/overview',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyFleetOverviewProvider::class,
        ),
    ]
)]
class AgencyFleetOverviewResource
{
    /**
     * @param array<string, int>                $kpis
     * @param list<array<string, mixed>>        $recentMaintenanceCases
     * @param list<array<string, mixed>>        $activeRentals
     * @param list<array<string, mixed>>        $expiringLicenses
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public array $kpis,
        public array $recentMaintenanceCases,
        public array $activeRentals,
        public array $expiringLicenses,
    ) {
    }
}
