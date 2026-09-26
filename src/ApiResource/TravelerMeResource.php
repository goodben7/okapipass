<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Dto\Traveler\UpdateTravelerMeDto;
use App\Provider\Traveler\TravelerMeProvider;
use App\State\Traveler\UpdateTravelerMeProcessor;

#[ApiResource(
    shortName: 'TravelerMe',
    operations: [
        new Get(
            uriTemplate: '/traveler/me',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerMeProvider::class,
        ),
        new Patch(
            uriTemplate: '/traveler/me',
            security: 'is_granted("ROLE_TRAVELER")',
            input: UpdateTravelerMeDto::class,
            output: TravelerMeResource::class,
            provider: TravelerMeProvider::class,
            processor: UpdateTravelerMeProcessor::class,
        ),
    ]
)]
final class TravelerMeResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public ?string $userId = null,
        public ?string $phone = null,
        public ?string $displayName = null,
        public ?string $email = null,
        public string $personType = 'TRAVELER',
        public ?string $idDocument = null,
        public ?string $emergencyContactName = null,
        public ?string $emergencyContactPhone = null,
        /** @var array<string, mixed>|null */
        public ?array $preferences = null,
    ) {
    }
}
