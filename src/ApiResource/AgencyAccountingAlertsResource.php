<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyAccountingAlertsProvider;

#[ApiResource(
    shortName: 'AgencyAccountingAlerts',
    operations: [
        new Get(
            uriTemplate: '/agency/accounting/alerts',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyAccountingAlertsProvider::class,
        ),
    ]
)]
final class AgencyAccountingAlertsResource
{
    /**
     * @param list<array<string, mixed>> $cashVariances
     * @param list<array<string, mixed>> $agedPendingPayments
     * @param array{rate: float, cancelled: int, total: int}|null $cancelRateSpike
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public array $cashVariances = [],
        public array $agedPendingPayments = [],
        public ?array $cancelRateSpike = null,
    ) {
    }
}
