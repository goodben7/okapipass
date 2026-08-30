<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyTicketResource;
use App\Manager\PublicAgencyPaymentManager;

/** @implements ProviderInterface<PublicAgencyTicketResource> */
final class PublicAgencyBookingTicketProvider implements ProviderInterface
{
    public function __construct(private PublicAgencyPaymentManager $manager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyTicketResource
    {
        return $this->manager->getTicketByPublicToken((string) ($uriVariables['publicToken'] ?? ''));
    }
}
