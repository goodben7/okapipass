<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyBookingResource;
use App\Manager\PublicAgencyBookingManager;

/** @implements ProviderInterface<PublicAgencyBookingResource> */
final class PublicAgencyBookingByTokenProvider implements ProviderInterface
{
    public function __construct(private PublicAgencyBookingManager $manager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingResource
    {
        return $this->manager->getByPublicToken((string) ($uriVariables['publicToken'] ?? ''));
    }
}
