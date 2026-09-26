<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\AgencyOffer;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyOfferManager;
use App\Repository\AgencyOfferRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProviderInterface<list<\App\Entity\AgencyOfferScheduleHistory>> */
final class AgencyOfferScheduleHistoryProvider implements ProviderInterface
{
    public function __construct(
        private AgencyOfferRepository $offers,
        private AgencyOfferManager $offerManager,
        private AgencyContext $agencyContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $this->agencyContext->requireAgency();
        $offerId = (string) ($uriVariables['offerId'] ?? '');
        $offer = $this->offers->find($offerId);
        if (!$offer instanceof AgencyOffer) {
            throw new UnavailableDataException('Offer not found.');
        }

        return $this->offerManager->listScheduleHistory($offer);
    }
}
