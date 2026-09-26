<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\AgencyOfferScheduleHistory;
use App\Provider\Agency\AgencyOfferScheduleHistoryProvider;

#[ApiResource(
    shortName: 'AgencyOfferScheduleHistory',
    normalizationContext: ['groups' => ['agency_offer_schedule_history:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/offers/{offerId}/schedule-history',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyOfferScheduleHistoryProvider::class,
            output: AgencyOfferScheduleHistory::class,
        ),
    ]
)]
final class AgencyOfferScheduleHistoryResource
{
}
