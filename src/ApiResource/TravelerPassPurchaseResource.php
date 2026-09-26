<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Traveler\CreateTravelerPassPurchaseDto;
use App\Entity\TravelerPass;
use App\State\Traveler\CreateTravelerPassPurchaseProcessor;

#[ApiResource(
    shortName: 'TravelerPassPurchase',
    normalizationContext: ['groups' => ['traveler_pass:get']],
    operations: [
        new Post(
            uriTemplate: '/traveler/passes/purchase',
            security: 'is_granted("ROLE_TRAVELER")',
            input: CreateTravelerPassPurchaseDto::class,
            output: TravelerPass::class,
            processor: CreateTravelerPassPurchaseProcessor::class,
            status: 201,
        ),
    ]
)]
final class TravelerPassPurchaseResource
{
}
