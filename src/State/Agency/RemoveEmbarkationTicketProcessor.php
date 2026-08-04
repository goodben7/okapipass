<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyEmbarkation;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyEmbarkationManager;
use App\Repository\AgencyEmbarkationRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<AgencyEmbarkation|null, void> */
final class RemoveEmbarkationTicketProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyEmbarkationManager $manager,
        private AgencyEmbarkationRepository $embarkations,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $embarkation = $data instanceof AgencyEmbarkation
            ? $data
            : $this->embarkations->find($uriVariables['id'] ?? null);
        if (null === $embarkation) {
            throw new UnavailableDataException('Embarkation not found.');
        }
        $this->agencyContext->assertOwns($embarkation->getAgency());
        $this->manager->removeTicket($embarkation, (string) ($uriVariables['ticketId'] ?? ''));

        return null;
    }
}
