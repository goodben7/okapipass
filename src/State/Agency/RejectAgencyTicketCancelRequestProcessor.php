<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\RejectAgencyTicketCancelRequestDto;
use App\Entity\AgencyTicketCancelRequest;
use App\Manager\AgencyTicketCancelManager;

/** @implements ProcessorInterface<RejectAgencyTicketCancelRequestDto, AgencyTicketCancelRequest> */
final class RejectAgencyTicketCancelRequestProcessor implements ProcessorInterface
{
    public function __construct(private AgencyTicketCancelManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyTicketCancelRequest
    {
        $request = $context['previous_data'] ?? null;
        if (!$request instanceof AgencyTicketCancelRequest) {
            throw new \InvalidArgumentException('Expected AgencyTicketCancelRequest as previous_data.');
        }

        $dto = $data instanceof RejectAgencyTicketCancelRequestDto
            ? $data
            : new RejectAgencyTicketCancelRequestDto();

        return $this->manager->reject($request, $dto);
    }
}
