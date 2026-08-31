<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyRentalContractDto;
use App\Manager\AgencyRentalContractManager;

/** @implements ProcessorInterface<CreateAgencyRentalContractDto, \App\Entity\AgencyRentalContract> */
final class CreateAgencyRentalContractProcessor implements ProcessorInterface
{
    public function __construct(private AgencyRentalContractManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyRentalContractDto);

        return $this->manager->create($data);
    }
}
