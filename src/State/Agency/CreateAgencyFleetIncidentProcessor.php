<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyFleetIncidentDto;
use App\Entity\AgencyFleetIncident;
use App\Manager\AgencyFleetIncidentManager;

/** @implements ProcessorInterface<CreateAgencyFleetIncidentDto, AgencyFleetIncident> */
final class CreateAgencyFleetIncidentProcessor implements ProcessorInterface
{
    public function __construct(private AgencyFleetIncidentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyFleetIncident
    {
        \assert($data instanceof CreateAgencyFleetIncidentDto);

        return $this->manager->create($data);
    }
}
