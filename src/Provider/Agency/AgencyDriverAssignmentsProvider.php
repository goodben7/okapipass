<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyDriverAssignmentsResource;
use App\Domain\Agency\AgencyFleetOverviewService;
use App\Entity\AgencyDriver;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyDriverRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProviderInterface<AgencyDriverAssignmentsResource> */
final class AgencyDriverAssignmentsProvider implements ProviderInterface
{
    public function __construct(
        private AgencyDriverRepository $drivers,
        private AgencyContext $agencyContext,
        private AgencyFleetOverviewService $fleetOverview,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyDriverAssignmentsResource
    {
        $driver = $this->drivers->find($uriVariables['driverId'] ?? null);
        if (!$driver instanceof AgencyDriver) {
            throw new UnavailableDataException('Driver not found.');
        }
        $this->agencyContext->assertOwns($driver->getAgency());

        return new AgencyDriverAssignmentsResource(
            driverId: (string) $driver->getId(),
            driverName: (string) $driver->getFullName(),
            assignments: $this->fleetOverview->driverAssignments($driver),
        );
    }
}
