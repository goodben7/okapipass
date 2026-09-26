<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyFleetEtaResource;
use App\Domain\Agency\AgencyPermission;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyEmbarkationRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProviderInterface<AgencyFleetEtaResource> */
final class AgencyFleetEtaProvider implements ProviderInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AgencyEmbarkationRepository $embarkations,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyFleetEtaResource
    {
        $this->agencyContext->requirePermission(AgencyPermission::FLEET_READ);
        $id = (string) ($uriVariables['embarkationId'] ?? '');
        $embarkation = $this->embarkations->find($id);
        if (null === $embarkation) {
            throw new UnavailableDataException('Embarkation not found.');
        }
        $this->agencyContext->assertOwns($embarkation->getAgency());

        return new AgencyFleetEtaResource(
            id: $id,
            etaMinutes: 15,
            lat: -4.3276,
            lng: 15.3136,
            status: 'STUB',
        );
    }
}
