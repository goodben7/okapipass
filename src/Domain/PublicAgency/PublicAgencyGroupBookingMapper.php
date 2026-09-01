<?php

namespace App\Domain\PublicAgency;

use App\ApiResource\Public\PublicAgencyBookingGroupResource;
use App\ApiResource\Public\PublicAgencyPassengerLineResource;
use App\Entity\AgencyBooking;
use App\Entity\AgencyBookingGroup;
use App\Entity\AgencyOffer;

final class PublicAgencyGroupBookingMapper
{
    public function fromGroup(AgencyBookingGroup $group, bool $isExpired = false): PublicAgencyBookingGroupResource
    {
        $offer = $group->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new \LogicException('Group has no offer.');
        }

        $passengers = [];
        $totalTicket = 0;
        $totalPass = 0;
        $currency = (string) $offer->getCurrency();
        $ticketUnit = (int) $offer->getTicketPrice();
        $groupTicket = $group->getTicket();

        foreach ($group->getBookings() as $booking) {
            if (!$booking instanceof AgencyBooking) {
                continue;
            }

            $passPrice = 0;
            $hasExistingPass = false;
            if ($groupTicket) {
                $passPrice = (int) round($groupTicket->getPassPrice() / max(1, $group->getBookings()->count()));
                $hasExistingPass = $groupTicket->hasExistingPass();
            }

            $lineTotal = $ticketUnit + $passPrice;

            $passengers[] = new PublicAgencyPassengerLineResource(
                bookingId: (string) $booking->getId(),
                seatNumber: (string) $booking->getSeatNumber(),
                passengerName: $this->nullableDisplayName($booking->getPassengerName(), $group->getGroupName()),
                passengerId: (string) $booking->getPassengerId(),
                passengerPhone: $this->nullableDisplayPhone($booking->getPassengerPhone()),
                okapiPassRef: $booking->getOkapiPassRef(),
                quote: [
                    'ticketPrice' => $ticketUnit,
                    'passPrice' => $passPrice,
                    'total' => $lineTotal,
                    'currency' => $currency,
                    'hasExistingPass' => $hasExistingPass,
                ],
            );

            $totalTicket += $ticketUnit;
            $totalPass += $passPrice;
        }

        usort($passengers, static fn (PublicAgencyPassengerLineResource $a, PublicAgencyPassengerLineResource $b): int => strcmp($a->seatNumber, $b->seatNumber));

        $pdfUrl = null;
        $ticketReference = null;
        if (null !== $groupTicket) {
            $ticketReference = $groupTicket->getReference();
            $pdfUrl = sprintf('/api/public/agency/booking-groups/%s/ticket/pdf', $group->getPublicToken());
        }

        return new PublicAgencyBookingGroupResource(
            publicToken: (string) $group->getPublicToken(),
            groupId: (string) $group->getId(),
            groupName: (string) $group->getGroupName(),
            contactPhone: $group->getContactPhone(),
            status: $group->getStatus(),
            paymentStatus: $group->getPaymentStatus(),
            expiresAt: $group->getExpiresAt()?->format(\DateTimeInterface::ATOM) ?? '',
            isExpired: $isExpired || $group->isExpired(),
            travelDate: $group->getTravelDate()?->format('Y-m-d') ?? '',
            quote: [
                'ticketPrice' => $totalTicket,
                'passPrice' => $totalPass,
                'total' => $totalTicket + $totalPass,
                'currency' => $currency,
                'hasExistingPass' => false,
                'passengerCount' => \count($passengers),
            ],
            offer: [
                'id' => (string) $offer->getId(),
                'label' => (string) $offer->getLabel(),
                'origin' => (string) $offer->getOrigin(),
                'destination' => (string) $offer->getDestination(),
                'departureTime' => (string) $offer->getDepartureTime(),
            ],
            passengers: $passengers,
            ticketReference: $ticketReference,
            pdfUrl: $pdfUrl,
        );
    }

    /**
     * @param array<int, array{ticketPrice: int, passPrice: int, total: int, currency: string, hasExistingPass: bool}> $lineQuotes keyed by passenger index
     */
    public function fromGroupWithLineQuotes(AgencyBookingGroup $group, array $lineQuotes, bool $isExpired = false): PublicAgencyBookingGroupResource
    {
        $resource = $this->fromGroup($group, $isExpired);
        foreach ($resource->passengers as $index => $passenger) {
            if (isset($lineQuotes[$index])) {
                $resource->passengers[$index] = new PublicAgencyPassengerLineResource(
                    bookingId: $passenger->bookingId,
                    seatNumber: $passenger->seatNumber,
                    passengerName: $passenger->passengerName,
                    passengerId: $passenger->passengerId,
                    passengerPhone: $passenger->passengerPhone,
                    okapiPassRef: $passenger->okapiPassRef,
                    quote: $lineQuotes[$index],
                );
            }
        }

        $totalTicket = 0;
        $totalPass = 0;
        foreach ($resource->passengers as $passenger) {
            $totalTicket += $passenger->quote['ticketPrice'];
            $totalPass += $passenger->quote['passPrice'];
        }
        $resource->quote = [
            'ticketPrice' => $totalTicket,
            'passPrice' => $totalPass,
            'total' => $totalTicket + $totalPass,
            'currency' => $resource->quote['currency'],
            'hasExistingPass' => false,
            'passengerCount' => \count($resource->passengers),
        ];

        return $resource;
    }

    private function nullableDisplayName(?string $name, ?string $groupName): ?string
    {
        $name = trim((string) $name);
        if ('' === $name || '—' === $name) {
            return null;
        }

        return $name;
    }

    private function nullableDisplayPhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        return '' === $phone ? null : $phone;
    }
}
