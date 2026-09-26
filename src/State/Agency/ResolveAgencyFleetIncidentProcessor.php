<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyFleetIncident;
use App\Manager\AgencyFleetIncidentManager;

/** @implements ProcessorInterface<mixed, AgencyFleetIncident> */
final class ResolveAgencyFleetIncidentProcessor implements ProcessorInterface
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

        return $this->manager->resolve($incident);
    }
}
