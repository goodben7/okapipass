<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyOffer;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyOfferManager;
use App\Repository\AgencyOfferRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<AgencyOffer|null, void> */
final class DeleteAgencyOfferProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyOfferManager $manager,
        private AgencyOfferRepository $offers,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $offer = $data instanceof AgencyOffer
            ? $data
            : $this->offers->find($uriVariables['id'] ?? null);

        if (null === $offer) {
            throw new UnavailableDataException('Offer not found.');
        }

        $this->agencyContext->assertOwns($offer->getAgency());
        $this->manager->delete($offer);

        return null;
    }
}
