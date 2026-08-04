<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyMeProvider;

/**
 * GET /api/agency/me — partner agency profile + current ONT Pass tariff (spec §6.1).
 */
#[ApiResource(
    shortName: 'AgencyMe',
    operations: [
        new Get(
            uriTemplate: '/agency/me',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyMeProvider::class,
        ),
    ]
)]
class AgencyMeResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        /** @var array<string, mixed> */
        public array $agency,
        /** @var array<string, mixed>|null */
        public ?array $ontPass,
        /** @var list<string> */
        public array $permissions,
        public string $staffRole = 'ADMIN',
    ) {
    }
}
