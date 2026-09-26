<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyFleetIncidentDto;
use App\Entity\AgencyFleetIncident;
use App\Manager\AgencyFleetIncidentManager;

/** @implements ProcessorInterface<UpdateAgencyFleetIncidentDto, AgencyFleetIncident> */
final class UpdateAgencyFleetIncidentProcessor implements ProcessorInterface
{
    public function __construct(private AgencyFleetIncidentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyFleetIncident
    {
        $incident = $context['previous_data'] ?? null;
        if (!$incident instanceof AgencyFleetIncident) {
            throw new \InvalidArgumentException('Expected AgencyFleetIncident as previous_data.');
        }
        \assert($data instanceof UpdateAgencyFleetIncidentDto);

        return $this->manager->update($incident, $data);
    }
}
