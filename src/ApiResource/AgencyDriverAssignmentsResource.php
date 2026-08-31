<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyDriverAssignmentsProvider;

/**
 * GET /api/agency/drivers/{driverId}/assignments (fleet F4).
 */
#[ApiResource(
    shortName: 'AgencyDriverAssignments',
    operations: [
        new Get(
            uriTemplate: '/agency/drivers/{driverId}/assignments',
            uriVariables: ['driverId'],
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyDriverAssignmentsProvider::class,
        ),
    ]
)]
class AgencyDriverAssignmentsResource
{
    /**
     * @param list<array<string, mixed>> $assignments
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $driverId,
        public string $driverName,
        public array $assignments,
    ) {
    }
}
