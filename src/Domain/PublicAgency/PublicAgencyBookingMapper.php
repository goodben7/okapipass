<?php

namespace App\Domain\PublicAgency;

use App\ApiResource\Public\PublicAgencyBookingResource;
use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;

final class PublicAgencyBookingMapper
{
    public function fromBooking(AgencyBooking $booking, bool $isExpired = false): PublicAgencyBookingResource
    {
        $offer = $booking->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new \LogicException('Booking has no offer.');
        }

        $ticketPrice = (int) $offer->getTicketPrice();
        $passPrice = max(0, (int) ($booking->getTicket()?->getPassPrice() ?? 0));

        // For pending bookings, recompute pass from stored ref via ticket if issued, else estimate from booking context
        if (null === $booking->getTicket()) {
            // Quote fields are filled by manager before persist; fallback minimal quote
            $passPrice = 0;
        }

        return new PublicAgencyBookingResource(
            publicToken: (string) $booking->getPublicToken(),
            bookingId: (string) $booking->getId(),
            status: $booking->getStatus(),
            paymentStatus: $booking->getPaymentStatus(),
            expiresAt: $booking->getExpiresAt()?->format(\DateTimeInterface::ATOM) ?? '',
            isExpired: $isExpired || $booking->isExpired(),
            passengerName: (string) $booking->getPassengerName(),
            passengerId: (string) $booking->getPassengerId(),
            passengerPhone: (string) $booking->getPassengerPhone(),
            seatNumber: (string) $booking->getSeatNumber(),
            travelDate: $booking->getTravelDate()?->format('Y-m-d') ?? '',
            okapiPassRef: $booking->getOkapiPassRef(),
            quote: [
                'ticketPrice' => $ticketPrice,
                'passPrice' => $passPrice,
                'total' => $ticketPrice + $passPrice,
                'currency' => $offer->getCurrency(),
                'hasExistingPass' => null !== $booking->getTicket()?->hasExistingPass()
                    ? $booking->getTicket()->hasExistingPass()
                    : false,
            ],
            offer: [
                'id' => (string) $offer->getId(),
                'label' => (string) $offer->getLabel(),
                'origin' => (string) $offer->getOrigin(),
                'destination' => (string) $offer->getDestination(),
                'departureTime' => (string) $offer->getDepartureTime(),
            ],
            ticketReference: $booking->getTicket()?->getReference(),
        );
    }

    /**
     * @param array{ticketPrice: int, passPrice: int, total: int, currency: string, hasExistingPass: bool} $quote
     */
    public function fromBookingWithQuote(AgencyBooking $booking, array $quote, bool $isExpired = false): PublicAgencyBookingResource
    {
        $resource = $this->fromBooking($booking, $isExpired);
        $resource->quote = $quote;

        return $resource;
    }
}
