<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyComplianceCalendarProvider;

#[ApiResource(
    shortName: 'AgencyComplianceCalendar',
    operations: [
        new Get(
            uriTemplate: '/agency/compliance/calendar',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyComplianceCalendarProvider::class,
        ),
    ]
)]
final class AgencyComplianceCalendarResource
{
    /**
     * @param array{open: int, overdue: int, dueSoon: int, upcoming: int, completed: int} $kpis
     * @param list<array<string, mixed>> $events
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $from,
        public string $to,
        public array $kpis,
        public array $events,
    ) {
    }
}
