<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyAccountingMarginProvider;

#[ApiResource(
    shortName: 'AgencyAccountingMargin',
    operations: [
        new Get(
            uriTemplate: '/agency/accounting/reports/margin',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyAccountingMarginProvider::class,
        ),
    ]
)]
final class AgencyAccountingMarginResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $from,
        public string $to,
        public int $ca = 0,
        public int $passOnt = 0,
        public int $commission = 0,
        public int $net = 0,
        public string $currency = 'CDF',
    ) {
    }
}
