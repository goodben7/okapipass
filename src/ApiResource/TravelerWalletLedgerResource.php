<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Provider\Traveler\TravelerWalletLedgerProvider;

#[ApiResource(
    shortName: 'TravelerWalletLedger',
    operations: [
        new GetCollection(
            uriTemplate: '/traveler/wallet/ledger',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerWalletLedgerProvider::class,
        ),
    ]
)]
final class TravelerWalletLedgerResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $type,
        public int $amount,
        public int $balanceAfter,
        public string $currency,
        public ?string $reference = null,
        public ?string $label = null,
        public ?string $createdAt = null,
    ) {
    }
}
