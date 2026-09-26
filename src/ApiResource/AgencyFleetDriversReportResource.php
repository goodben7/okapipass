<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFleetDriversReportProvider;

#[ApiResource(
    shortName: 'AgencyFleetDriversReport',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/reports/drivers',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyFleetDriversReportProvider::class,
        ),
    ]
)]
final class AgencyFleetDriversReportResource
{
    /**
     * @param list<array<string, mixed>> $drivers
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $from,
        public string $to,
        public array $drivers = [],
    ) {
    }
}
