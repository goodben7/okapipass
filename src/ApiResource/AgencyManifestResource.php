<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\ReleaseNoShowsDto;
use App\Provider\Agency\AgencyManifestProvider;
use App\State\Agency\ReleaseNoShowsProcessor;

#[ApiResource(
    shortName: 'AgencyManifest',
    operations: [
        new Get(
            uriTemplate: '/agency/manifests',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyManifestProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/manifests/release-noshows',
            security: AgencyPortalAccess::EXPRESSION,
            input: ReleaseNoShowsDto::class,
            output: AgencyManifestReleaseResource::class,
            processor: ReleaseNoShowsProcessor::class,
            read: false,
            status: 200,
        ),
    ]
)]
final class AgencyManifestResource
{
    /**
     * @param list<array<string, mixed>> $tickets
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $offerId,
        public string $travelDate,
        public array $tickets,
        public int $boardedCount,
        public int $issuedCount,
        public int $noShowCount,
    ) {
    }
}
