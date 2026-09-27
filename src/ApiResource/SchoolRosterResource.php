<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\SchoolRosterProvider;

#[ApiResource(
    shortName: 'SchoolRoster',
    operations: [
        new Get(
            uriTemplate: '/agency/school/roster',
            security: AgencyPortalAccess::EXPRESSION,
            provider: SchoolRosterProvider::class,
        ),
    ]
)]
final class SchoolRosterResource
{
    /**
     * @param list<array<string, mixed>> $students
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $contractId,
        public string $date,
        public ?string $embarkationId = null,
        public array $students = [],
        public ?string $transportId = null,
        public ?string $transportLabel = null,
        public ?string $plateNumber = null,
    ) {
    }
}
