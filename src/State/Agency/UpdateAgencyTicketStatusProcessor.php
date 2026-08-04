<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyTicketStatusDto;
use App\Entity\AgencyTicket;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyBookingManager;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UpdateAgencyTicketStatusDto, AgencyTicket> */
final class UpdateAgencyTicketStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyBookingManager $manager,
        private AgencyTicketRepository $tickets,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyTicketStatusDto);
        $ticket = $this->tickets->find($uriVariables['id'] ?? null);
        if (null === $ticket) {
            throw new UnavailableDataException('Ticket not found.');
        }
        $this->agencyContext->assertOwns($ticket->getAgency());

        return $this->manager->updateTicketStatus($ticket, (string) $data->status);
    }
}
