<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyDriverDto;
use App\Manager\AgencyDriverManager;

/** @implements ProcessorInterface<CreateAgencyDriverDto, \App\Entity\AgencyDriver> */
final class CreateAgencyDriverProcessor implements ProcessorInterface
{
    public function __construct(private AgencyDriverManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyDriverDto);

        return $this->manager->create($data);
    }
}
