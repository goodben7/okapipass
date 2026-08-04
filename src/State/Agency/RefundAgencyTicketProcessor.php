<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyTicket;
use App\Manager\AgencyPaymentManager;

/** @implements ProcessorInterface<AgencyTicket, AgencyTicket> */
final class RefundAgencyTicketProcessor implements ProcessorInterface
{
    public function __construct(private AgencyPaymentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof AgencyTicket);

        return $this->manager->refundTicket($data);
    }
}
