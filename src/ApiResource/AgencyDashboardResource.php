<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyDashboardProvider;

#[ApiResource(
    shortName: 'AgencyDashboard',
    operations: [
        new Get(
            uriTemplate: '/agency/dashboard',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyDashboardProvider::class,
        ),
    ]
)]
class AgencyDashboardResource
{
    /**
     * @param list<array<string, mixed>> $recentTickets
     * @param list<array<string, mixed>> $recentDeclarations
     * @param list<array<string, mixed>> $departuresToday
     * @param array<string, int>         $fleet
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public int $ticketsToday,
        public int $activeBookings,
        public int $fptDue,
        public int $activeTransports,
        public array $recentTickets,
        public array $recentDeclarations,
        public array $departuresToday,
        public array $fleet,
        public int $cashRiskCount = 0,
        public float $cancelRate7d = 0.0,
        public float $cancelRateToday = 0.0,
    ) {
    }
}
