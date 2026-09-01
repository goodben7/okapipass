<?php

namespace App\Domain\Agency;

use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Exception\ConflictException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyTicketRepository;


final class SeatOccupancyService
{
    public function __construct(
        private SeatLayoutBuilder $layoutBuilder,
        private AgencyBookingRepository $bookings,
        private AgencyTicketRepository $tickets,
    ) {
    }

    /**
     * @return array{
     *     offerId: string,
     *     travelDate: string,
     *     capacity: int,
     *     availableCount: int,
     *     isFull: bool,
     *     layout: array,
     *     occupiedSeats: list<string>
     * }
     */
    public function availability(AgencyOffer $offer, \DateTimeImmutable $travelDate, ?string $excludeBookingId = null): array
    {
        $transport = $offer->getTransport();
        if (null === $transport) {
            throw new UnprocessableEntityException('Offer has no transport.');
        }

        $layout = $this->layoutBuilder->build((string) $transport->getKind(), (int) $transport->getCapacity());
        $occupied = $this->occupiedSeats($offer, $travelDate, $excludeBookingId);
        $availableCount = max(0, $layout['capacity'] - \count($occupied));

        return [
            'offerId' => (string) $offer->getId(),
            'travelDate' => $travelDate->format('Y-m-d'),
            'capacity' => $layout['capacity'],
            'availableCount' => $availableCount,
            'isFull' => 0 === $availableCount,
            'layout' => [
                'kind' => $layout['kind'],
                'rows' => $layout['rows'],
                'columns' => $layout['columns'],
                'aisleAfter' => $layout['aisleAfter'],
                'seatIds' => $layout['seatIds'],
            ],
            'occupiedSeats' => array_values($occupied),
        ];
    }

    /**
     * @return list<string>
     */
    public function occupiedSeats(AgencyOffer $offer, \DateTimeImmutable $travelDate, ?string $excludeBookingId = null): array
    {
        $seats = [];

        foreach ($this->bookings->findActiveSeats($offer, $travelDate, $excludeBookingId) as $seat) {
            $seats[$seat] = $seat;
        }

        foreach ($this->tickets->findActiveManualSeats($offer, $travelDate) as $seat) {
            $seats[$seat] = $seat;
        }

        $list = array_values($seats);
        sort($list);

        return $list;
    }

    public function normalizeSeat(?string $seatNumber): string
    {
        return strtoupper(trim((string) $seatNumber));
    }

    public function assertSeatSelectable(
        AgencyOffer $offer,
        \DateTimeImmutable $travelDate,
        ?string $seatNumber,
        ?string $excludeBookingId = null,
        ?string $excludeSeatNumber = null,
    ): string {
        $seat = $this->normalizeSeat($seatNumber);
        if ('' === $seat) {
            throw new UnprocessableEntityException('Sélectionnez un siège sur le plan du bus.');
        }

        $transport = $offer->getTransport();
        if (null === $transport) {
            throw new UnprocessableEntityException('Offer has no transport.');
        }

        if (!$this->layoutBuilder->isValidSeat((string) $transport->getKind(), (int) $transport->getCapacity(), $seat)) {
            throw new UnprocessableEntityException(sprintf('Siège %s invalide pour ce véhicule.', $seat));
        }

        $occupied = $this->occupiedSeats($offer, $travelDate, $excludeBookingId);
        if (null !== $excludeSeatNumber) {
            $exclude = $this->normalizeSeat($excludeSeatNumber);
            $occupied = array_values(array_filter(
                $occupied,
                static fn (string $s): bool => $s !== $exclude,
            ));
        }

        if (\in_array($seat, $occupied, true)) {
            throw new ConflictException(sprintf('Le siège %s est déjà réservé.', $seat));
        }

        $capacity = (int) $transport->getCapacity();
        if (\count($occupied) >= $capacity) {
            throw new ConflictException('Bus complet — aucune place disponible.');
        }

        return $seat;
    }

    /**
     * @param list<string|null> $seatNumbers
     *
     * @return list<string>
     */
    public function assertSeatsSelectable(
        AgencyOffer $offer,
        \DateTimeImmutable $travelDate,
        array $seatNumbers,
        ?string $excludeBookingId = null,
    ): array {
        if ([] === $seatNumbers) {
            throw new UnprocessableEntityException('Sélectionnez au moins un siège.');
        }

        $normalized = [];
        $seen = [];
        foreach ($seatNumbers as $seatNumber) {
            $seat = $this->normalizeSeat($seatNumber);
            if ('' === $seat) {
                throw new UnprocessableEntityException('Sélectionnez un siège sur le plan du bus.');
            }
            if (isset($seen[$seat])) {
                throw new ConflictException(sprintf('Le siège %s est sélectionné plusieurs fois.', $seat));
            }
            $seen[$seat] = true;
            $normalized[] = $seat;
        }

        $transport = $offer->getTransport();
        if (null === $transport) {
            throw new UnprocessableEntityException('Offer has no transport.');
        }

        foreach ($normalized as $seat) {
            if (!$this->layoutBuilder->isValidSeat((string) $transport->getKind(), (int) $transport->getCapacity(), $seat)) {
                throw new UnprocessableEntityException(sprintf('Siège %s invalide pour ce véhicule.', $seat));
            }
        }

        $occupied = $this->occupiedSeats($offer, $travelDate, $excludeBookingId);
        $pending = [];
        foreach ($normalized as $seat) {
            if (\in_array($seat, $occupied, true) || \in_array($seat, $pending, true)) {
                throw new ConflictException(sprintf('Le siège %s est déjà réservé.', $seat));
            }
            $pending[] = $seat;
        }

        $capacity = (int) $transport->getCapacity();
        if (\count($occupied) + \count($normalized) > $capacity) {
            throw new ConflictException('Bus complet — places insuffisantes pour ce groupe.');
        }

        return $normalized;
    }
}
