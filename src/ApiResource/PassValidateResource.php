<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\PassValidateProvider;

#[ApiResource(
    shortName: 'PassValidate',
    operations: [
        new Get(
            uriTemplate: '/passes/validate',
            security: 'is_granted("ROLE_PARTNER") or is_granted("ROLE_ONT_AGENT") or is_granted("ROLE_ONT_ADMIN")',
            provider: PassValidateProvider::class,
        ),
    ]
)]
class PassValidateResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $ref,
        public bool $valid,
        public ?string $holder,
        public string $status,
        public ?string $expiresAt,
        public ?string $warning = null,
    ) {
    }
}
