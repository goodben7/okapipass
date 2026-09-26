<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFillForecastProvider;

#[ApiResource(
    shortName: 'AgencyFillForecast',
    operations: [
        new Get(
            uriTemplate: '/agency/analytics/fill-forecast',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyFillForecastProvider::class,
        ),
    ]
)]
final class AgencyFillForecastResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $offerId,
        public string $date,
        public int $ticketsSold = 0,
        public int $capacity = 0,
        public float $occupancyPercent = 0.0,
        public float $projectedFillPercent = 0.0,
    ) {
    }
}
