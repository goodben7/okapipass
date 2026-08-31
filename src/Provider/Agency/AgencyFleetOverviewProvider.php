<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyFleetOverviewResource;
use App\Domain\Agency\AgencyFleetOverviewService;
use App\Service\Agency\AgencyContext;

/** @implements ProviderInterface<AgencyFleetOverviewResource> */
final class AgencyFleetOverviewProvider implements ProviderInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AgencyFleetOverviewService $fleetOverview,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyFleetOverviewResource
    {
        $agency = $this->agencyContext->requireAgency();
        $data = $this->fleetOverview->buildOverview($agency);

        return new AgencyFleetOverviewResource(
            id: 'overview',
            kpis: $data['kpis'],
            recentMaintenanceCases: $data['recentMaintenanceCases'],
            activeRentals: $data['activeRentals'],
            expiringLicenses: $data['expiringLicenses'],
        );
    }
}
