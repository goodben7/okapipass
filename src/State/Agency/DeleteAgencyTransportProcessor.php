<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyTransport;
use App\Manager\AgencyTransportManager;

/** @implements ProcessorInterface<AgencyTransport, void> */
final class DeleteAgencyTransportProcessor implements ProcessorInterface
{
    public function __construct(private AgencyTransportManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $transport = $data instanceof AgencyTransport
            ? $data
            : $this->manager->getOwned((string) $uriVariables['id']);

        $this->manager->delete($transport);

        return null;
    }
}
