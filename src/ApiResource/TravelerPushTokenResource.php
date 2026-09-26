<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Traveler\RegisterTravelerPushTokenDto;
use App\State\Traveler\RegisterTravelerPushTokenProcessor;

#[ApiResource(
    shortName: 'TravelerPushToken',
    operations: [
        new Post(
            uriTemplate: '/traveler/push-tokens',
            security: 'is_granted("ROLE_TRAVELER")',
            input: RegisterTravelerPushTokenDto::class,
            output: TravelerPushTokenResource::class,
            processor: RegisterTravelerPushTokenProcessor::class,
            status: 201,
        ),
    ]
)]
final class TravelerPushTokenResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id = 'push',
        public ?string $deviceToken = null,
        public ?string $platform = null,
        public string $channel = 'stored',
        public string $note = 'SMS remains the primary notification channel; push is stub storage only.',
    ) {
    }
}
