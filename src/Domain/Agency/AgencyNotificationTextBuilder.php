<?php

namespace App\Domain\Agency;

use App\Contract\AgencySmsSenderInterface;
use App\Entity\AgencyBooking;
use App\Entity\AgencyTicket;

/**
 * Builds SMS / WhatsApp preview texts (spec §6.11).
 */
final class AgencyNotificationTextBuilder
{
    public function bookingSms(AgencyBooking $booking): string
    {
        $offer = $booking->getOffer();

        return sprintf(
            'OkapiPass: Reservation %s confirmee. Trajet %s → %s le %s, siege %s. Presentez-vous avec une piece d\'identite.',
            $booking->getId(),
            $offer?->getOrigin() ?? '?',
            $offer?->getDestination() ?? '?',
            $booking->getTravelDate()?->format('d/m/Y') ?? '?',
            $booking->getSeatNumber(),
        );
    }

    public function ticketSms(AgencyTicket $ticket): string
    {
        $offer = $ticket->getOffer();
        $total = $ticket->getTicketPrice() + $ticket->getPassPrice();
        $seatLabel = $ticket->isGroupTicket()
            ? implode(', ', $ticket->getGroupSeatList())
            : (string) $ticket->getSeatNumber();

        $typeLabel = $ticket->isGroupTicket() ? 'Billet groupe' : 'Billet';

        return sprintf(
            'OkapiPass: %s %s emis. Trajet %s → %s le %s, sieges %s. Total %d %s.',
            $typeLabel,
            $ticket->getReference(),
            $offer?->getOrigin() ?? '?',
            $offer?->getDestination() ?? '?',
            $ticket->getTravelDate()?->format('d/m/Y') ?? '?',
            $seatLabel,
            $total,
            $ticket->getCurrency(),
        );
    }

    public function whatsappUrl(string $phone, string $message): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '243'.substr($digits, 1);
        }

        return sprintf('https://wa.me/%s?text=%s', $digits, rawurlencode($message));
    }
}
