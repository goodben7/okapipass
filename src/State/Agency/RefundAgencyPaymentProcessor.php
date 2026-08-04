<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyPayment;
use App\Manager\AgencyPaymentManager;

/** @implements ProcessorInterface<AgencyPayment, AgencyPayment> */
final class RefundAgencyPaymentProcessor implements ProcessorInterface
{
    public function __construct(private AgencyPaymentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof AgencyPayment);

        return $this->manager->refundPayment($data);
    }
}
