<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Traveler\TravelerWalletProvider;

#[ApiResource(
    shortName: 'TravelerWallet',
    operations: [
        new Get(
            uriTemplate: '/traveler/wallet',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerWalletProvider::class,
        ),
    ]
)]
final class TravelerWalletResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public int $balance = 0,
        public string $currency = 'CDF',
        public ?string $updatedAt = null,
    ) {
    }
}
