<?php

namespace App\Domain\Agency;

use App\Entity\AgencyTicket;

/**
 * QR payload for agency tickets (aligned for front qr-utils consumption).
 */
final class AgencyQrPayloadBuilder
{
    public function build(AgencyTicket $ticket): string
    {
        $payload = [
            'v' => 1,
            'type' => 'agency_ticket',
            'ref' => $ticket->getReference(),
            'seat' => $ticket->getSeatNumber(),
            'date' => $ticket->getTravelDate()?->format('Y-m-d'),
            'offer' => $ticket->getOffer()?->getId(),
            'agency' => $ticket->getAgency()?->getId(),
            'passenger' => $ticket->getPassengerName(),
            'pass' => $ticket->getOkapiPassRef(),
        ];

        return base64_encode(json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function printData(AgencyTicket $ticket): array
    {
        $offer = $ticket->getOffer();

        return [
            'ticket' => [
                'id' => $ticket->getId(),
                'reference' => $ticket->getReference(),
                'status' => $ticket->getStatus(),
                'passengerName' => $ticket->getPassengerName(),
                'passengerId' => $ticket->getPassengerId(),
                'passengerPhone' => $ticket->getPassengerPhone(),
                'seatNumber' => $ticket->getSeatNumber(),
                'travelDate' => $ticket->getTravelDate()?->format('Y-m-d'),
                'ticketPrice' => $ticket->getTicketPrice(),
                'passPrice' => $ticket->getPassPrice(),
                'currency' => $ticket->getCurrency(),
                'okapiPassRef' => $ticket->getOkapiPassRef(),
                'hasExistingPass' => $ticket->hasExistingPass(),
                'notes' => $ticket->getNotes(),
            ],
            'offer' => [
                'id' => $offer?->getId(),
                'label' => $offer?->getLabel(),
                'origin' => $offer?->getOrigin(),
                'destination' => $offer?->getDestination(),
                'departureTime' => $offer?->getDepartureTime(),
            ],
            'agency' => [
                'id' => $ticket->getAgency()?->getId(),
                'name' => $ticket->getAgency()?->getName(),
            ],
            'qrPayload' => $ticket->getQrPayload() ?: $this->build($ticket),
        ];
    }
}
