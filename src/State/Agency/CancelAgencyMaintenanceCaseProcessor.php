<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyMaintenanceCase;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyMaintenanceCaseManager;
use App\Repository\AgencyMaintenanceCaseRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<null, AgencyMaintenanceCase> */
final class CancelAgencyMaintenanceCaseProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyMaintenanceCaseManager $manager,
        private AgencyMaintenanceCaseRepository $cases,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $case = $this->requireCase($uriVariables);

        return $this->manager->cancel($case);
    }

    /** @param array<string, mixed> $uriVariables */
    private function requireCase(array $uriVariables): AgencyMaintenanceCase
    {
        $case = $this->cases->find($uriVariables['id'] ?? null);
        if (!$case instanceof AgencyMaintenanceCase) {
            throw new UnavailableDataException('Maintenance case not found.');
        }
        $this->agencyContext->assertOwns($case->getAgency());

        return $case;
    }
}
