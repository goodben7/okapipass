<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\TripAssignResultDto;
use App\Dto\Agency\UnassignTripTransportDto;
use App\Entity\AgencyEmbarkation;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyEmbarkationManager;
use App\Repository\AgencyEmbarkationRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UnassignTripTransportDto|null, TripAssignResultDto> */
final class UnassignTripTransportProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyEmbarkationManager $manager,
        private AgencyEmbarkationRepository $embarkations,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TripAssignResultDto
    {
        $dto = $data instanceof UnassignTripTransportDto ? $data : new UnassignTripTransportDto();

        $id = (string) ($uriVariables['id'] ?? '');
        if ('' === $id && isset($context['previous_data']) && $context['previous_data'] instanceof AgencyEmbarkation) {
            $id = (string) $context['previous_data']->getId();
        }
        $embarkation = $this->embarkations->find($id);
        if (!$embarkation instanceof AgencyEmbarkation) {
            throw new UnavailableDataException('Trip (embarkation) not found.');
        }
        $this->agencyContext->assertOwns($embarkation->getAgency());

        return $this->manager->unassign($embarkation, $dto);
    }
}
