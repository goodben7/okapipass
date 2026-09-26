<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\RecordAgencyTicketBaggageDto;
use App\Entity\AgencyTicket;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyBaggageManager;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<RecordAgencyTicketBaggageDto, \App\Dto\Agency\RecordBaggageResult> */
final class RecordAgencyTicketBaggageProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyBaggageManager $manager,
        private AgencyTicketRepository $tickets,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof RecordAgencyTicketBaggageDto);

        $ticket = $this->tickets->find($uriVariables['id'] ?? null);
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('Ticket not found.');
        }
        $this->agencyContext->assertOwns($ticket->getAgency());

        return $this->manager->recordBaggage($ticket, (int) $data->kg);
    }
}
