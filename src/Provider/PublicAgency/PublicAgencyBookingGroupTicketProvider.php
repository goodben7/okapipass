<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyTicketResource;
use App\Manager\PublicAgencyGroupBookingManager;

/** @implements ProviderInterface<PublicAgencyTicketResource> */
final class PublicAgencyBookingGroupTicketProvider implements ProviderInterface
{
    public function __construct(private PublicAgencyGroupBookingManager $manager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyTicketResource
    {
        return $this->manager->getTicketByPublicToken((string) ($uriVariables['publicToken'] ?? ''));
    }
}
