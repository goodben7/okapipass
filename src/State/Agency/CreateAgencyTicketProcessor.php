<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyTicketDto;
use App\Manager\AgencyBookingManager;

/** @implements ProcessorInterface<CreateAgencyTicketDto, \App\Entity\AgencyTicket> */
final class CreateAgencyTicketProcessor implements ProcessorInterface
{
    public function __construct(private AgencyBookingManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyTicketDto);

        return $this->manager->createManualTicket($data);
    }
}
