<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\DeclarationSummaryProvider;

#[ApiResource(
    shortName: 'AgencyDeclarationSummary',
    operations: [
        new Get(
            uriTemplate: '/agency/declarations/summary',
            security: AgencyPortalAccess::EXPRESSION,
            provider: DeclarationSummaryProvider::class,
        ),
    ]
)]
class AgencyDeclarationSummaryResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public int $fptDue,
        public string $currency,
        public int $draft,
        public int $submitted,
        public int $validated,
        public int $paid,
        /** @var array<string, int> */
        public array $byCurrency = [],
    ) {
    }
}
