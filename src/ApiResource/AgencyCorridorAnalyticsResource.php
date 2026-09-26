<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyCorridorAnalyticsProvider;

#[ApiResource(
    shortName: 'AgencyCorridorAnalytics',
    operations: [
        new Get(
            uriTemplate: '/agency/analytics/corridors',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyCorridorAnalyticsProvider::class,
        ),
    ]
)]
final class AgencyCorridorAnalyticsResource
{
    /**
     * @param list<array{origin: string, destination: string, ticketsSold: int, capacityEstimate: int, occupancyPercent: float}> $corridors
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $from,
        public string $to,
        public array $corridors = [],
    ) {
    }
}
