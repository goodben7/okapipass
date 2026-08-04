<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyTransportDto;
use App\Manager\AgencyTransportManager;

/** @implements ProcessorInterface<CreateAgencyTransportDto, \App\Entity\AgencyTransport> */
final class CreateAgencyTransportProcessor implements ProcessorInterface
{
    public function __construct(private AgencyTransportManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyTransportDto);

        return $this->manager->create($data);
    }
}
