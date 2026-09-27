<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\CreateSchoolDepartureDto;
use App\Entity\AgencyEmbarkation;
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
    ]
)]
final class SchoolDepartureResource
{
}
