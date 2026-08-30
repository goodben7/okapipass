<?php

namespace App\ApiResource\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\PublicAgency\PublicAgencyOfferQuoteProvider;

#[ApiResource(
    shortName: 'PublicAgencyOfferQuote',
    operations: [
        new Get(
            uriTemplate: '/public/agency/offers/{offerId}/quote',
            uriVariables: ['offerId'],
            provider: PublicAgencyOfferQuoteProvider::class,
        ),
    ]
)]
final class PublicAgencyOfferQuoteResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $offerId,
        public int $ticketPrice,
        public int $passPrice,
        public int $total,
        public string $currency,
        public bool $hasExistingPass,
        public ?string $okapiPassRef = null,
    ) {
    }
}
