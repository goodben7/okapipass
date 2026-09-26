<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyAccountingAlertsResource;
use App\Manager\AccountingAgencyManager;

/** @implements ProviderInterface<AgencyAccountingAlertsResource> */
final class AgencyAccountingAlertsProvider implements ProviderInterface
{
    public function __construct(private AccountingAgencyManager $accounting)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyAccountingAlertsResource
    {
        $result = $this->accounting->alerts($this->accounting->requireAgency());

        return new AgencyAccountingAlertsResource(
            id: 'alerts',
            cashVariances: $result['cashVariances'],
            agedPendingPayments: $result['agedPendingPayments'],
            cancelRateSpike: $result['cancelRateSpike'],
        );
    }
}
