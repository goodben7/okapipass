<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencyOfferQuoteResource;
use App\Domain\Agency\AgencyDiscountService;
use App\Domain\Agency\AgencyOfferEffectivePriceResolver;
use App\Domain\Agency\AgencyPricingService;
use App\Domain\PublicAgency\PublicAgencyCatalogService;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<PublicAgencyOfferQuoteResource> */
final class PublicAgencyOfferQuoteProvider implements ProviderInterface
{
    public function __construct(
        private PublicAgencyCatalogService $catalog,
        private AgencyPricingService $pricing,
        private AgencyDiscountService $discounts,
        private AgencyOfferEffectivePriceResolver $effectivePrice,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyOfferQuoteResource
    {
        $offerId = (string) ($uriVariables['offerId'] ?? '');
        $offer = $this->catalog->requireOnlineOffer($offerId);

        $request = $this->requestStack->getCurrentRequest();
        $okapiPassRef = $request?->query->get('okapiPassRef');
        $okapiPassRef = \is_string($okapiPassRef) && '' !== trim($okapiPassRef) ? trim($okapiPassRef) : null;

        $promoCode = $request?->query->get('promoCode');
        $promoCode = \is_string($promoCode) && '' !== trim($promoCode) ? trim($promoCode) : null;

        $phone = $request?->query->get('phone');
        $phone = \is_string($phone) && '' !== trim($phone) ? trim($phone) : null;

        $travelDateRaw = $request?->query->get('travelDate');
        $travelDate = null;
        if (\is_string($travelDateRaw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $travelDateRaw)) {
            $travelDate = new \DateTimeImmutable($travelDateRaw);
        }

        $quote = $this->pricing->quote($okapiPassRef);
        $ticketPrice = $this->effectivePrice->resolve($offer, $travelDate);
        $passPrice = (int) $quote['passPrice'];

        $discountAmount = 0;
        $appliedPromoCode = null;
        if (null !== $promoCode || null !== $phone) {
            $agency = $offer->getAgency();
            if (null !== $agency) {
                $resolved = $this->discounts->resolveDiscount(
                    $agency,
                    $ticketPrice,
                    $promoCode,
                    $phone,
                    $offer,
                );
                $discountAmount = $resolved['discountAmount'];
                $appliedPromoCode = $resolved['promoCode'];
            }
        }

        $finalTicketPrice = max(0, $ticketPrice - $discountAmount);

        return new PublicAgencyOfferQuoteResource(
            offerId: $offerId,
            ticketPrice: $ticketPrice,
            passPrice: $passPrice,
            total: $finalTicketPrice + $passPrice,
            currency: $offer->getCurrency(),
            hasExistingPass: (bool) $quote['hasExistingPass'],
            okapiPassRef: $okapiPassRef,
            discountAmount: $discountAmount,
            promoCode: $appliedPromoCode ?? $promoCode,
            finalTicketPrice: $finalTicketPrice,
        );
    }
}
