<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyBookingGroupTicketsResource;
use App\Manager\PublicAgencyGroupBookingManager;

/** @implements ProviderInterface<PublicAgencyBookingGroupTicketsResource> */
final class PublicAgencyBookingGroupTicketsProvider implements ProviderInterface
{
    public function __construct(private PublicAgencyGroupBookingManager $manager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingGroupTicketsResource
    {
        return $this->manager->getTicketsByPublicToken((string) ($uriVariables['publicToken'] ?? ''));
    }
}
