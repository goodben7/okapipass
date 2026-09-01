<?php

namespace App\State\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Public\PublicAgencyBookingGroupResource;
use App\Manager\PublicAgencyGroupBookingManager;

/** @implements ProcessorInterface<mixed, PublicAgencyBookingGroupResource> */
final class CancelPublicAgencyBookingGroupProcessor implements ProcessorInterface
{
    public function __construct(private PublicAgencyGroupBookingManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingGroupResource
    {
        return $this->manager->cancelIfUnpaid((string) ($uriVariables['publicToken'] ?? ''));
    }
}
