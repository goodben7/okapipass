<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyTicketPrintResource;
use App\Domain\Agency\AgencyQrPayloadBuilder;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProviderInterface<AgencyTicketPrintResource> */
final class AgencyTicketPrintProvider implements ProviderInterface
{
    public function __construct(
        private AgencyTicketRepository $tickets,
        private AgencyContext $agencyContext,
        private AgencyQrPayloadBuilder $qr,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyTicketPrintResource
    {
        $ticket = $this->tickets->find($uriVariables['id'] ?? null);
        if (null === $ticket) {
            throw new UnavailableDataException('Ticket not found.');
        }
        $this->agencyContext->assertOwns($ticket->getAgency());

        $data = $this->qr->printData($ticket);

        return new AgencyTicketPrintResource(
            id: (string) $ticket->getId(),
            ticket: $data['ticket'],
            offer: $data['offer'],
            agency: $data['agency'],
            qrPayload: $data['qrPayload'],
        );
    }
}
