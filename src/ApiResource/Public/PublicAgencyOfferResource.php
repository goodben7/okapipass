<?php

namespace App\ApiResource\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Provider\PublicAgency\PublicAgencyOfferCollectionProvider;
use App\Provider\PublicAgency\PublicAgencyOfferItemProvider;

#[ApiResource(
    shortName: 'PublicAgencyOffer',
    operations: [
        new GetCollection(
            uriTemplate: '/public/agency/offers',
            provider: PublicAgencyOfferCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/public/agency/offers/{id}',
            provider: PublicAgencyOfferItemProvider::class,
        ),
    ]
)]
final class PublicAgencyOfferResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $label,
        public string $origin,
        public string $destination,
        public int $ticketPrice,
        public string $currency,
        public string $departureTime,
        public int $durationMinutes,
        public string $agencyId,
        public string $agencyName,
        public string $transportKind,
        public int $transportCapacity,
        public int $bookingHoldMinutes,
    ) {
    }
}
