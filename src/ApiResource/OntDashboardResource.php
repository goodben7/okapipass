<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Ont\OntDashboardProvider;

#[ApiResource(
    shortName: 'OntDashboard',
    operations: [
        new Get(
            uriTemplate: '/ont/dashboard',
            security: 'is_granted("ROLE_ONT_ADMIN") or is_granted("ROLE_ONT_AGENT") or is_granted("ROLE_SUPER_ADMIN")',
            provider: OntDashboardProvider::class,
        ),
    ]
)]
final class OntDashboardResource
{
    /**
     * @param array{
     *     agenciesActive: int,
     *     agenciesTotal: int,
     *     ticketsToday: int,
     *     ticketsMonth: int,
     *     passesActive: int,
     *     passesIssuedMonth: int,
     *     fptDraft: int,
     *     fptSubmitted: int,
     *     fptPaid: int,
     *     fptDue: int,
     *     currency: string,
     *     paymentsPending: int,
     *     paymentsPaidToday: int
     * } $kpis
     * @param list<array{periodMonth: string, draft: int, submitted: int, paid: int, total: int}> $fptByMonth
     * @param list<array<string, mixed>> $recentDeclarations
     * @param list<array{agencyId: string, agencyName: string, fptDue: int, currency: string}> $topAgenciesByFptDue
     * @param list<array{type: string, severity: string, message: string, agencyId: ?string, periodMonth: ?string, declarationId: ?string}> $alerts
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $generatedAt,
        public string $periodMonth,
        public array $kpis,
        public array $fptByMonth,
        public array $recentDeclarations,
        public array $topAgenciesByFptDue,
        public array $alerts,
        public int $pollSuggestedSeconds = 15,
    ) {
    }
}
