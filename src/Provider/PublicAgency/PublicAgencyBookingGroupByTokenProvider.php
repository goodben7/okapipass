<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyBookingGroupResource;
use App\Manager\PublicAgencyGroupBookingManager;

/** @implements ProviderInterface<PublicAgencyBookingGroupResource> */
final class PublicAgencyBookingGroupByTokenProvider implements ProviderInterface
{
    public function __construct(private PublicAgencyGroupBookingManager $manager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingGroupResource
    {
        return $this->manager->getByPublicToken((string) ($uriVariables['publicToken'] ?? ''));
    }
}
