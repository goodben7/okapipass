<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyBookingDto;
use App\Manager\AgencyBookingManager;

/** @implements ProcessorInterface<CreateAgencyBookingDto, array> */
final class CreateAgencyBookingProcessor implements ProcessorInterface
{
    public function __construct(private AgencyBookingManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyBookingDto);

        return $this->manager->create($data);
    }
}
