<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\AssignTripTransportDto;
use App\Dto\Agency\TripAssignResultDto;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyEmbarkationManager;
use App\Repository\AgencyEmbarkationRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<AssignTripTransportDto, TripAssignResultDto> */
final class AssignSchoolDepartureTransportProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyEmbarkationManager $manager,
        private AgencyEmbarkationRepository $embarkations,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TripAssignResultDto
    {
        \assert($data instanceof AssignTripTransportDto);

        $embarkation = $this->embarkations->find($uriVariables['id'] ?? null);
        if (null === $embarkation) {
            throw new UnavailableDataException('School departure (embarkation) not found.');
        }
        $this->agencyContext->assertOwns($embarkation->getAgency());

        return $this->manager->assign($embarkation, $data);
    }
}
