<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Dto\Traveler\CreateWalletTopupDto;
use App\Provider\Traveler\TravelerWalletTopupItemProvider;
use App\State\Traveler\CreateWalletTopupProcessor;

#[ApiResource(
    shortName: 'TravelerWalletTopup',
    operations: [
        new Post(
            uriTemplate: '/traveler/wallet/topups',
            security: 'is_granted("ROLE_TRAVELER")',
            input: CreateWalletTopupDto::class,
            output: self::class,
            processor: CreateWalletTopupProcessor::class,
            status: 201,
        ),
        new Get(
            uriTemplate: '/traveler/wallet/topups/{id}',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerWalletTopupItemProvider::class,
        ),
    ]
)]
final class TravelerWalletTopupResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public int $amount = 0,
        public string $currency = 'CDF',
        public string $status = 'PENDING',
        public string $method = 'MOBILE_MONEY',
        public ?string $phone = null,
        public ?string $providerTx = null,
        public ?string $paidAt = null,
        public ?string $createdAt = null,
    ) {
    }
}
