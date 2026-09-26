<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\TravelerPass;
use App\Provider\Traveler\TravelerPassCollectionProvider;

#[ApiResource(
    shortName: 'TravelerPass',
    normalizationContext: ['groups' => ['traveler_pass:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/traveler/passes',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerPassCollectionProvider::class,
            output: TravelerPass::class,
        ),
    ]
)]
final class TravelerPassCollectionResource
{
}
