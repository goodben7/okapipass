<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProviderInterface<\App\Entity\AgencyTicket> */
final class AgencyTicketByReferenceProvider implements ProviderInterface
{
    public function __construct(
        private AgencyTicketRepository $tickets,
        private AgencyContext $agencyContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $ref = (string) ($uriVariables['reference'] ?? '');
        $ticket = $this->tickets->findOneByReference($ref);
        if (null === $ticket) {
            throw new UnavailableDataException(sprintf('Ticket "%s" not found.', $ref));
        }
        $this->agencyContext->assertOwns($ticket->getAgency());

        return $ticket;
    }
}
