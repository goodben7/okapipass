<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Provider\Agency\OntPassTariffProvider;

#[ApiResource(
    shortName: 'OntPassTariff',
    operations: [
        new GetCollection(
            uriTemplate: '/ont/pass-tariffs',
            security: AgencyPortalAccess::EXPRESSION,
            provider: OntPassTariffProvider::class,
            paginationEnabled: false,
        ),
    ]
)]
class OntPassTariffResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $code,
        public string $label,
        public float|int $price,
        public string $currency,
        public string $transportType,
        public bool $active,
    ) {
    }
}
