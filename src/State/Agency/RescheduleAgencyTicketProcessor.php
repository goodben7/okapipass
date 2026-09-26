<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\RescheduleAgencyTicketDto;
use App\Entity\AgencyTicket;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyTicketRescheduleManager;

/** @implements ProcessorInterface<RescheduleAgencyTicketDto, AgencyTicket> */
final class RescheduleAgencyTicketProcessor implements ProcessorInterface
{
    public function __construct(private AgencyTicketRescheduleManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyTicket
    {
        $ticket = $context['previous_data'] ?? null;
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('Ticket not found.');
        }
        \assert($data instanceof RescheduleAgencyTicketDto);

        return $this->manager->reschedule($ticket, $data);
    }
}
