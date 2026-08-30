<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyOfferQuoteResource;
use App\Domain\Agency\AgencyPricingService;
use App\Domain\PublicAgency\PublicAgencyCatalogService;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<PublicAgencyOfferQuoteResource> */
final class PublicAgencyOfferQuoteProvider implements ProviderInterface
{
    public function __construct(
        private PublicAgencyCatalogService $catalog,
        private AgencyPricingService $pricing,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyOfferQuoteResource
    {
        $offerId = (string) ($uriVariables['offerId'] ?? '');
        $offer = $this->catalog->requireOnlineOffer($offerId);

        $okapiPassRef = $this->requestStack->getCurrentRequest()?->query->get('okapiPassRef');
        $okapiPassRef = \is_string($okapiPassRef) && '' !== trim($okapiPassRef) ? trim($okapiPassRef) : null;

        $quote = $this->pricing->quote($okapiPassRef);
        $ticketPrice = (int) $offer->getTicketPrice();
        $passPrice = (int) $quote['passPrice'];

        return new PublicAgencyOfferQuoteResource(
            offerId: $offerId,
            ticketPrice: $ticketPrice,
            passPrice: $passPrice,
            total: $ticketPrice + $passPrice,
            currency: $offer->getCurrency(),
            hasExistingPass: (bool) $quote['hasExistingPass'],
            okapiPassRef: $okapiPassRef,
        );
    }
}
