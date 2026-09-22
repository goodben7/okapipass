<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\BootstrapAgencyObligationsDto;
use App\State\Agency\BootstrapAgencyObligationsProcessor;

#[ApiResource(
    shortName: 'AgencyObligationBootstrap',
    operations: [
        new Post(
            uriTemplate: '/agency/obligations/bootstrap',
            security: AgencyPortalAccess::EXPRESSION,
            input: BootstrapAgencyObligationsDto::class,
            output: AgencyObligationBootstrapResource::class,
            processor: BootstrapAgencyObligationsProcessor::class,
            read: false,
            status: 201,
        ),
    ]
)]
final class AgencyObligationBootstrapResource
{
    /**
     * @param list<array<string, mixed>> $obligations
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public int $createdCount,
        public array $obligations,
    ) {
    }
}
