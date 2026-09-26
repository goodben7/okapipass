<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyAccountingReconciliationProvider;

#[ApiResource(
    shortName: 'AgencyAccountingReconciliation',
    operations: [
        new Get(
            uriTemplate: '/agency/accounting/reconciliation',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyAccountingReconciliationProvider::class,
        ),
    ]
)]
final class AgencyAccountingReconciliationResource
{
    /**
     * @param list<string> $unmatchedPaymentIds
     * @param list<string> $unmatchedJournalSourceIds
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $from,
        public string $to,
        public int $paidPaymentsCount = 0,
        public int $paidAmount = 0,
        public int $journalCreditCount = 0,
        public int $journalCreditAmount = 0,
        public array $unmatchedPaymentIds = [],
        public array $unmatchedJournalSourceIds = [],
    ) {
    }
}
