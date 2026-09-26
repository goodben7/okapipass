<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyTicketCancelRequest;
use App\Manager\AgencyTicketCancelManager;

/** @implements ProcessorInterface<mixed, AgencyTicketCancelRequest> */
final class ApproveAgencyTicketCancelRequestProcessor implements ProcessorInterface
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

        return $this->manager->approve($request);
    }
}
