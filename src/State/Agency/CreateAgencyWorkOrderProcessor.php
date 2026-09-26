<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyWorkOrderDto;
use App\Manager\AgencyWorkOrderManager;

/** @implements ProcessorInterface<CreateAgencyWorkOrderDto, \App\Entity\AgencyWorkOrder> */
final class CreateAgencyWorkOrderProcessor implements ProcessorInterface
{
    public function __construct(private AgencyWorkOrderManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyWorkOrderDto);

        return $this->manager->create($data);
    }
}
