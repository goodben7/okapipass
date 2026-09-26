<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyFleetCostByCorridorProvider;

#[ApiResource(
    shortName: 'AgencyFleetCostByCorridor',
    operations: [
        new Get(
            uriTemplate: '/agency/fleet/reports/cost-by-corridor',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyFleetCostByCorridorProvider::class,
        ),
    ]
)]
final class AgencyFleetCostByCorridorResource
{
    /**
     * @param list<array<string, mixed>> $corridors
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
