<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyPosKpiProvider;

#[ApiResource(
    shortName: 'AgencyPosKpi',
    operations: [
        new Get(
            uriTemplate: '/agency/pos/kpi',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyPosKpiProvider::class,
        ),
    ]
)]
final class AgencyPosKpiResource
{
    /**
     * @param array{CASH: int, MM: int, CARD: int} $mix
     * @param list<array{sellerId: string, tickets: int, amount: int}> $bySeller
     * @param list<array{pointOfSale: string, tickets: int, amount: int}> $byPointOfSale
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $date,
        public int $ticketsCount = 0,
        public int $salesCount = 0,
        public int $caTotal = 0,
        public array $mix = ['CASH' => 0, 'MM' => 0, 'CARD' => 0],
        public array $bySeller = [],
        public array $byPointOfSale = [],
    ) {
    }
}
