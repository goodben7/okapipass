<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Traveler\TravelerLoyaltyProvider;

#[ApiResource(
    shortName: 'TravelerLoyalty',
    normalizationContext: ['groups' => ['loyalty_account:get']],
    operations: [
        new Get(
            uriTemplate: '/traveler/loyalty',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerLoyaltyProvider::class,
        ),
    ]
)]
final class TravelerLoyaltyResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public ?string $agencyId = null,
        public ?string $phone = null,
        public int $points = 0,
    ) {
    }
}
