<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyOfferResource;
use App\Domain\PublicAgency\PublicAgencyCatalogService;
use App\Domain\PublicAgency\PublicAgencyOfferMapper;

/** @implements ProviderInterface<PublicAgencyOfferResource> */
final class PublicAgencyOfferItemProvider implements ProviderInterface
{
    public function __construct(
        private PublicAgencyCatalogService $catalog,
        private PublicAgencyOfferMapper $mapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyOfferResource
    {
        $offer = $this->catalog->requireOnlineOffer((string) ($uriVariables['id'] ?? ''));

        return $this->mapper->fromEntity($offer);
    }
}
