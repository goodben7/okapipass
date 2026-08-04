<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyTicketSeatDto;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyBookingManager;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UpdateAgencyTicketSeatDto, \App\Entity\AgencyTicket> */
final class UpdateAgencyTicketSeatProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyBookingManager $manager,
        private AgencyTicketRepository $tickets,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyTicketSeatDto);
        $ticket = $this->tickets->find($uriVariables['id'] ?? null);
        if (null === $ticket) {
            throw new UnavailableDataException('Ticket not found.');
        }
        $this->agencyContext->assertOwns($ticket->getAgency());

        return $this->manager->updateTicketSeat($ticket, $data);
    }
}
