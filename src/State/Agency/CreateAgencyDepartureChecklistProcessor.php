<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyDepartureChecklistDto;
use App\Manager\AgencyFleetOpsManager;

/** @implements ProcessorInterface<CreateAgencyDepartureChecklistDto, \App\Entity\AgencyDepartureChecklist> */
final class CreateAgencyDepartureChecklistProcessor implements ProcessorInterface
{
    public function __construct(private AgencyFleetOpsManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyDepartureChecklistDto);

        return $this->manager->createDepartureChecklist($data);
    }
}
