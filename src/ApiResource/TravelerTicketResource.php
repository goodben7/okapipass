<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Dto\Traveler\ShareTravelerTicketDto;
use App\Provider\Traveler\TravelerTicketCollectionProvider;
use App\Provider\Traveler\TravelerTicketItemProvider;
use App\State\Traveler\ShareTravelerTicketProcessor;

#[ApiResource(
    shortName: 'TravelerTicket',
    operations: [
        new GetCollection(
            uriTemplate: '/traveler/tickets',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerTicketCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/traveler/tickets/{id}',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerTicketItemProvider::class,
        ),
        new Post(
            uriTemplate: '/traveler/tickets/{id}/share',
            security: 'is_granted("ROLE_TRAVELER")',
            input: ShareTravelerTicketDto::class,
            output: TravelerTicketShareResource::class,
            processor: ShareTravelerTicketProcessor::class,
            read: false,
            status: 200,
        ),
    ]
)]
final class TravelerTicketResource
{
    /**
     * @param array<string, mixed>|null $agency
     * @param array<string, mixed>|null $offer
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public ?string $reference = null,
        public ?string $passengerName = null,
        public ?string $passengerPhone = null,
        public ?string $seatNumber = null,
        public ?string $travelDate = null,
        public ?string $status = null,
        public ?int $ticketPrice = null,
        public ?int $passPrice = null,
        public ?int $discountAmount = null,
        public ?string $promoCode = null,
        public ?string $currency = null,
        public ?array $agency = null,
        public ?array $offer = null,
        public ?string $pdfUrl = null,
    ) {
    }
}
