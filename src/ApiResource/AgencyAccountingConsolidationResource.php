<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyAccountingConsolidationProvider;

#[ApiResource(
    shortName: 'AgencyAccountingConsolidation',
    operations: [
        new Get(
            uriTemplate: '/agency/accounting/consolidation',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyAccountingConsolidationProvider::class,
        ),
    ]
)]
final class AgencyAccountingConsolidationResource
{
    /** @param list<array<string, mixed>> $depots */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $from,
        public string $to,
        public array $depots = [],
    ) {
    }
}
