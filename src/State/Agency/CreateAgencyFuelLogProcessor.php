<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyFuelLogDto;
use App\Manager\AgencyFleetOpsManager;

/** @implements ProcessorInterface<CreateAgencyFuelLogDto, \App\Entity\AgencyFuelLog> */
final class CreateAgencyFuelLogProcessor implements ProcessorInterface
{
    public function __construct(private AgencyFleetOpsManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyFuelLogDto);

        return $this->manager->createFuelLog($data);
    }
}
