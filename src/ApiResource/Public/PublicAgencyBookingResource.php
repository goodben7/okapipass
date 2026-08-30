<?php

namespace App\ApiResource\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Dto\Public\CreatePublicAgencyBookingDto;
use App\Dto\Public\PayPublicAgencyBookingDto;
use App\Provider\PublicAgency\PublicAgencyBookingByTokenProvider;
use App\Provider\PublicAgency\PublicAgencyBookingTicketProvider;
use App\State\PublicAgency\CancelPublicAgencyBookingProcessor;
use App\State\PublicAgency\CheckPublicAgencyPaymentProcessor;
use App\State\PublicAgency\CreatePublicAgencyBookingProcessor;
use App\State\PublicAgency\PayPublicAgencyBookingProcessor;

#[ApiResource(
    shortName: 'PublicAgencyBooking',
    operations: [
        new Post(
            uriTemplate: '/public/agency/bookings',
            input: CreatePublicAgencyBookingDto::class,
            processor: CreatePublicAgencyBookingProcessor::class,
            status: 201,
        ),
        new Get(
            uriTemplate: '/public/agency/bookings/{publicToken}',
            uriVariables: ['publicToken'],
            provider: PublicAgencyBookingByTokenProvider::class,
        ),
        new Post(
            uriTemplate: '/public/agency/bookings/{publicToken}/cancel',
            uriVariables: ['publicToken'],
            input: false,
            deserialize: false,
            validate: false,
            provider: PublicAgencyBookingByTokenProvider::class,
            processor: CancelPublicAgencyBookingProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/public/agency/bookings/{publicToken}/pay',
            uriVariables: ['publicToken'],
            input: PayPublicAgencyBookingDto::class,
            output: PublicAgencyBookingPaymentResource::class,
            provider: PublicAgencyBookingByTokenProvider::class,
            processor: PayPublicAgencyBookingProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/public/agency/bookings/{publicToken}/pay/check-status',
            uriVariables: ['publicToken'],
            input: false,
            deserialize: false,
            validate: false,
            output: PublicAgencyBookingPaymentResource::class,
            provider: PublicAgencyBookingByTokenProvider::class,
            processor: CheckPublicAgencyPaymentProcessor::class,
            status: 200,
        ),
        new Get(
            uriTemplate: '/public/agency/bookings/{publicToken}/ticket',
            uriVariables: ['publicToken'],
            output: PublicAgencyTicketResource::class,
            provider: PublicAgencyBookingTicketProvider::class,
        ),
    ]
)]
final class PublicAgencyBookingResource
{
    /**
     * @param array{ticketPrice: int, passPrice: int, total: int, currency: string, hasExistingPass: bool} $quote
     * @param array{id: string, label: string, origin: string, destination: string, departureTime: string}  $offer
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $publicToken,
        public string $bookingId,
        public string $status,
        public string $paymentStatus,
        public string $expiresAt,
        public bool $isExpired,
        public string $passengerName,
        public string $passengerId,
        public string $passengerPhone,
        public string $seatNumber,
        public string $travelDate,
        public ?string $okapiPassRef,
        public array $quote,
        public array $offer,
        public ?string $ticketReference = null,
    ) {
    }
}
