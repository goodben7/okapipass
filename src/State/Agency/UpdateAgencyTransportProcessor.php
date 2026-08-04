<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyTransportDto;
use App\Manager\AgencyTransportManager;

/** @implements ProcessorInterface<UpdateAgencyTransportDto, \App\Entity\AgencyTransport> */
final class UpdateAgencyTransportProcessor implements ProcessorInterface
{
    public function __construct(private AgencyTransportManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyTransportDto);
        $transport = $this->manager->getOwned((string) $uriVariables['id']);

        return $this->manager->update($transport, $data);
    }
}
