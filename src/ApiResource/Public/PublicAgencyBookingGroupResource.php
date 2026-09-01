<?php

namespace App\ApiResource\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Dto\Public\CreatePublicAgencyBookingGroupDto;
use App\Dto\Public\PayPublicAgencyBookingDto;
use App\Provider\PublicAgency\PublicAgencyBookingGroupByTokenProvider;
use App\Provider\PublicAgency\PublicAgencyBookingGroupTicketProvider;
use App\Provider\PublicAgency\PublicAgencyBookingGroupTicketsProvider;
use App\State\PublicAgency\CancelPublicAgencyBookingGroupProcessor;
use App\State\PublicAgency\CheckPublicAgencyBookingGroupPaymentProcessor;
use App\State\PublicAgency\CreatePublicAgencyBookingGroupProcessor;
use App\State\PublicAgency\PayPublicAgencyBookingGroupProcessor;

#[ApiResource(
    shortName: 'PublicAgencyBookingGroup',
    operations: [
        new Post(
            uriTemplate: '/public/agency/booking-groups',
            input: CreatePublicAgencyBookingGroupDto::class,
            processor: CreatePublicAgencyBookingGroupProcessor::class,
            status: 201,
        ),
        new Get(
            uriTemplate: '/public/agency/booking-groups/{publicToken}',
            uriVariables: ['publicToken'],
            provider: PublicAgencyBookingGroupByTokenProvider::class,
        ),
        new Post(
            uriTemplate: '/public/agency/booking-groups/{publicToken}/cancel',
            uriVariables: ['publicToken'],
            input: false,
            deserialize: false,
            validate: false,
            provider: PublicAgencyBookingGroupByTokenProvider::class,
            processor: CancelPublicAgencyBookingGroupProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/public/agency/booking-groups/{publicToken}/pay',
            uriVariables: ['publicToken'],
            input: PayPublicAgencyBookingDto::class,
            output: PublicAgencyBookingGroupPaymentResource::class,
            provider: PublicAgencyBookingGroupByTokenProvider::class,
            processor: PayPublicAgencyBookingGroupProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/public/agency/booking-groups/{publicToken}/pay/check-status',
            uriVariables: ['publicToken'],
            input: false,
            deserialize: false,
            validate: false,
            output: PublicAgencyBookingGroupPaymentResource::class,
            provider: PublicAgencyBookingGroupByTokenProvider::class,
            processor: CheckPublicAgencyBookingGroupPaymentProcessor::class,
            status: 200,
        ),
        new Get(
            uriTemplate: '/public/agency/booking-groups/{publicToken}/ticket',
            uriVariables: ['publicToken'],
            output: PublicAgencyTicketResource::class,
            provider: PublicAgencyBookingGroupTicketProvider::class,
        ),
        new Get(
            uriTemplate: '/public/agency/booking-groups/{publicToken}/tickets',
            uriVariables: ['publicToken'],
            output: PublicAgencyBookingGroupTicketsResource::class,
            provider: PublicAgencyBookingGroupTicketsProvider::class,
        ),
    ]
)]
final class PublicAgencyBookingGroupResource
{
    /**
     * @param array{ticketPrice: int, passPrice: int, total: int, currency: string, hasExistingPass: bool, passengerCount: int} $quote
     * @param array{id: string, label: string, origin: string, destination: string, departureTime: string} $offer
     * @param list<PublicAgencyPassengerLineResource> $passengers
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $publicToken,
        public string $groupId,
        public string $groupName,
        public ?string $contactPhone,
        public string $status,
        public string $paymentStatus,
        public string $expiresAt,
        public bool $isExpired,
        public string $travelDate,
        public array $quote,
        public array $offer,
        public array $passengers,
        public ?string $ticketReference = null,
        public ?string $pdfUrl = null,
    ) {
    }
}
