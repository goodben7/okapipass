<?php

namespace App\State\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Public\CreatePublicAgencyBookingDto;
use App\Manager\PublicAgencyBookingManager;

/** @implements ProcessorInterface<CreatePublicAgencyBookingDto, object> */
final class CreatePublicAgencyBookingProcessor implements ProcessorInterface
{
    public function __construct(private PublicAgencyBookingManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreatePublicAgencyBookingDto);

        return $this->manager->createOnline($data);
    }
}
