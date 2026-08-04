<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyOfferDto;
use App\Entity\AgencyOffer;
use App\Manager\AgencyOfferManager;
use App\Repository\AgencyOfferRepository;
use App\Service\Agency\AgencyContext;
use App\Exception\UnavailableDataException;

/** @implements ProcessorInterface<UpdateAgencyOfferDto, AgencyOffer> */
final class UpdateAgencyOfferProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyOfferManager $manager,
        private AgencyOfferRepository $offers,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyOfferDto);

        $offer = $this->offers->find($uriVariables['id'] ?? null);
        if (null === $offer) {
            throw new UnavailableDataException('Offer not found.');
        }
        $this->agencyContext->assertOwns($offer->getAgency());

        return $this->manager->update($offer, $data);
    }
}
