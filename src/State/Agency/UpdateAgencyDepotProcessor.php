<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyDepotDto;
use App\Entity\AgencyDepot;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyDepotManager;

/** @implements ProcessorInterface<UpdateAgencyDepotDto, AgencyDepot> */
final class UpdateAgencyDepotProcessor implements ProcessorInterface
{
    public function __construct(private AgencyDepotManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyDepot
    {
        $entity = $context['previous_data'] ?? $data;
        if (!$entity instanceof AgencyDepot) {
            throw new UnavailableDataException('AgencyDepot not found.');
        }
        \assert($data instanceof UpdateAgencyDepotDto);

        return $this->manager->update($entity, $data);
    }
}
