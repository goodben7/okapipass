<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\RecordSchoolAttendanceDto;
use App\Entity\SchoolAttendance;
use App\State\Agency\RecordSchoolAttendanceProcessor;

#[ApiResource(
    shortName: 'SchoolAttendanceRecord',
    normalizationContext: ['groups' => ['school_attendance:get']],
    operations: [
        new Post(
            uriTemplate: '/agency/school/attendance',
            security: AgencyPortalAccess::EXPRESSION,
            input: RecordSchoolAttendanceDto::class,
            output: SchoolAttendance::class,
            processor: RecordSchoolAttendanceProcessor::class,
            read: false,
            status: 200,
        ),
    ]
)]
final class SchoolAttendanceRecordResource
{
}
