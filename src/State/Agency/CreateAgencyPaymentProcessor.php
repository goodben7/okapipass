<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyPaymentDto;
use App\Manager\AgencyPaymentManager;

/** @implements ProcessorInterface<CreateAgencyPaymentDto, \App\Entity\AgencyPayment> */
final class CreateAgencyPaymentProcessor implements ProcessorInterface
{
    public function __construct(private AgencyPaymentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyPaymentDto);

        return $this->manager->collect($data);
    }
}
