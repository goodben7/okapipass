<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\DeclarationSummaryProvider;

#[ApiResource(
    shortName: 'AgencyDeclarationSummary',
    operations: [
        new Get(
            uriTemplate: '/agency/declarations/summary',
            security: 'is_granted("ROLE_PARTNER")',
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
        public int $paid,
        /** @var array<string, int> */
        public array $byCurrency = [],
    ) {
    }
}
