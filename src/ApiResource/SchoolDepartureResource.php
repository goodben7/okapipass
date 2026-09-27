<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\AssignTripTransportDto;
use App\Dto\Agency\CreateSchoolDepartureDto;
use App\Dto\Agency\TripAssignResultDto;
use App\Entity\AgencyEmbarkation;
use App\State\Agency\AssignSchoolDepartureTransportProcessor;
use App\State\Agency\CreateSchoolDepartureProcessor;

#[ApiResource(
    shortName: 'SchoolDeparture',
    normalizationContext: ['groups' => ['agency_embarkation:get']],
    operations: [
        new Post(
            uriTemplate: '/agency/school/departures',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateSchoolDepartureDto::class,
            output: AgencyEmbarkation::class,
            processor: CreateSchoolDepartureProcessor::class,
            read: false,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/school/departures/{id}/assign-transport',
            security: AgencyPortalAccess::EXPRESSION,
            input: AssignTripTransportDto::class,
            output: TripAssignResultDto::class,
            uriVariables: ['id'],
            processor: AssignSchoolDepartureTransportProcessor::class,
            read: false,
            status: 200,
            normalizationContext: ['groups' => ['trip_assign:get']],
        ),
    ]
)]
final class SchoolDepartureResource
{
}
