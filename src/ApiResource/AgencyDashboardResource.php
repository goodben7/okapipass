<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyDashboardProvider;

#[ApiResource(
    shortName: 'AgencyDashboard',
    operations: [
        new Get(
            uriTemplate: '/agency/dashboard',
            security: 'is_granted("ROLE_PARTNER")',
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
    ) {
    }
}
