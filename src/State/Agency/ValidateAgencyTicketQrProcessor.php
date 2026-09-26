<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Agency\AgencyPermission;
use App\Domain\Agency\AgencyQrPayloadBuilder;
use App\Dto\Agency\ValidateAgencyTicketQrDto;
use App\Entity\AgencyTicket;
use App\Manager\TravelerPassManager;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProcessorInterface<ValidateAgencyTicketQrDto, AgencyTicket> */
final class ValidateAgencyTicketQrProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyQrPayloadBuilder $qr,
        private AgencyContext $agencyContext,
        private TravelerPassManager $passManager,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyTicket
    {
        \assert($data instanceof ValidateAgencyTicketQrDto);
        $this->agencyContext->requirePermission(AgencyPermission::EMBARKATION_WRITE);

        $ticket = $this->qr->validateAndConsume((string) $data->token);
        $this->agencyContext->assertOwns($ticket->getAgency());

        $this->passManager->consumeTripForTicket($ticket);
        $this->em->flush();

        return $ticket;
    }
}
