<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyLoyaltyBenefitsProvider;

#[ApiResource(
    shortName: 'AgencyLoyaltyBenefits',
    operations: [
        new Get(
            uriTemplate: '/agency/loyalty/benefits',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyLoyaltyBenefitsProvider::class,
        ),
    ]
)]
final class AgencyLoyaltyBenefitsResource
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $phone = null,
        public array $items = [],
        public int $totalDiscount = 0,
    ) {
    }
}
