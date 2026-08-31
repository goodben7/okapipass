<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyMaintenanceCaseDto;
use App\Manager\AgencyMaintenanceCaseManager;

/** @implements ProcessorInterface<CreateAgencyMaintenanceCaseDto, \App\Entity\AgencyMaintenanceCase> */
final class CreateAgencyMaintenanceCaseProcessor implements ProcessorInterface
{
    public function __construct(private AgencyMaintenanceCaseManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyMaintenanceCaseDto);

        return $this->manager->create($data);
    }
}
