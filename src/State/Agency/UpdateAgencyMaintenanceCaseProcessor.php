<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyMaintenanceCaseDto;
use App\Entity\AgencyMaintenanceCase;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyMaintenanceCaseManager;
use App\Repository\AgencyMaintenanceCaseRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UpdateAgencyMaintenanceCaseDto, AgencyMaintenanceCase> */
final class UpdateAgencyMaintenanceCaseProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyMaintenanceCaseManager $manager,
        private AgencyMaintenanceCaseRepository $cases,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyMaintenanceCaseDto);

        $case = $this->cases->find($uriVariables['id'] ?? null);
        if (!$case instanceof AgencyMaintenanceCase) {
            throw new UnavailableDataException('Maintenance case not found.');
        }
        $this->agencyContext->assertOwns($case->getAgency());

        return $this->manager->update($case, $data);
    }
}
