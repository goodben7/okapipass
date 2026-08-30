<?php

namespace App\Domain\PublicAgency;

use App\ApiResource\Public\PublicAgencyOfferResource;
use App\Entity\AgencyOffer;

final class PublicAgencyOfferMapper
{
    public function fromEntity(AgencyOffer $offer): PublicAgencyOfferResource
    {
        $agency = $offer->getAgency();
        $transport = $offer->getTransport();

        return new PublicAgencyOfferResource(
            id: (string) $offer->getId(),
            label: (string) $offer->getLabel(),
            origin: (string) $offer->getOrigin(),
            destination: (string) $offer->getDestination(),
            ticketPrice: (int) $offer->getTicketPrice(),
            currency: $offer->getCurrency(),
            departureTime: (string) $offer->getDepartureTime(),
            durationMinutes: (int) $offer->getDurationMinutes(),
            agencyId: (string) $agency?->getId(),
            agencyName: (string) $agency?->getName(),
            transportKind: (string) $transport?->getKind(),
            transportCapacity: (int) ($transport?->getCapacity() ?? 0),
            bookingHoldMinutes: $offer->getBookingHoldMinutes(),
        );
    }
}
