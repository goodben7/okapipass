<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyTicketCancelRequestDto;
use App\Entity\AgencyTicketCancelRequest;
use App\Manager\AgencyTicketCancelManager;

/** @implements ProcessorInterface<CreateAgencyTicketCancelRequestDto, AgencyTicketCancelRequest> */
final class CreateAgencyTicketCancelRequestProcessor implements ProcessorInterface
{
    public function __construct(private AgencyTicketCancelManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyTicketCancelRequest
    {
        \assert($data instanceof CreateAgencyTicketCancelRequestDto);

        return $this->manager->createRequest((string) ($uriVariables['id'] ?? ''), $data);
    }
}
