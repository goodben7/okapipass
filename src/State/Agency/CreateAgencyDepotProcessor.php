<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyDepotDto;
use App\Entity\AgencyDepot;
use App\Manager\AgencyDepotManager;

/** @implements ProcessorInterface<CreateAgencyDepotDto, AgencyDepot> */
final class CreateAgencyDepotProcessor implements ProcessorInterface
{
    public function __construct(private AgencyDepotManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyDepot
    {
        \assert($data instanceof CreateAgencyDepotDto);

        return $this->manager->create($data);
    }
}
