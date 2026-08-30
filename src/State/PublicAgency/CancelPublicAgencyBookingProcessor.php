<?php

namespace App\State\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Public\PublicAgencyBookingResource;
use App\Manager\PublicAgencyBookingManager;

/** @implements ProcessorInterface<PublicAgencyBookingResource, PublicAgencyBookingResource> */
final class CancelPublicAgencyBookingProcessor implements ProcessorInterface
{
    public function __construct(private PublicAgencyBookingManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingResource
    {
        \assert($data instanceof PublicAgencyBookingResource);

        return $this->manager->cancelIfUnpaid($data->publicToken);
    }
}
