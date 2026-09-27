<?php

namespace App\Domain\Agency;

use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use App\Exception\ConflictException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyTicketRepository;

final class SeatOccupancyService
{
    public function __construct(
        private SeatLayoutBuilder $layoutBuilder,
        private AgencyBookingRepository $bookings,
        private AgencyTicketRepository $tickets,
        private AgencyEmbarkationRepository $embarkations,
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
     *     occupiedSeats: list<string>,
     *     vehicleUnassigned: bool,
     *     transportId: ?string,
     *     transportLabel: ?string,
     *     plateNumber: ?string,
     *     embarkationId: ?string,
     *     soldCount: int,
     *     seatMode: string
     * }
     */
    public function availability(AgencyOffer $offer, \DateTimeImmutable $travelDate, ?string $excludeBookingId = null): array
    {
        $resolved = $this->resolveTransportForDate($offer, $travelDate);
        $transport = $resolved['transport'];
        if (null === $transport) {
            throw new UnprocessableEntityException('Offer has no transport.');
        }

        $capacity = (int) $transport->getCapacity();
        $sold = $this->soldCount($offer, $travelDate, $excludeBookingId);

        if ($offer->isCapacityOnly()) {
            $availableCount = max(0, $capacity - $sold);

            return [
                'offerId' => (string) $offer->getId(),
                'travelDate' => $travelDate->format('Y-m-d'),
                'capacity' => $capacity,
                'availableCount' => $availableCount,
                'isFull' => 0 === $availableCount,
                'layout' => [
                    'kind' => 'CAPACITY_ONLY',
                    'rows' => [],
                    'columns' => [],
                    'aisleAfter' => null,
                    'seatIds' => [],
                ],
                'occupiedSeats' => [],
                'vehicleUnassigned' => $resolved['vehicleUnassigned'],
                'transportId' => $transport->getId(),
                'transportLabel' => $transport->getLabel(),
                'plateNumber' => $transport->getPlateNumber(),
                'embarkationId' => $resolved['embarkationId'],
                'soldCount' => $sold,
                'seatMode' => $offer->getSeatMode(),
            ];
        }

        $layout = $this->layoutBuilder->build((string) $transport->getKind(), $capacity);
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
            'vehicleUnassigned' => $resolved['vehicleUnassigned'],
            'transportId' => $transport->getId(),
            'transportLabel' => $transport->getLabel(),
            'plateNumber' => $transport->getPlateNumber(),
            'embarkationId' => $resolved['embarkationId'],
            'soldCount' => $sold,
            'seatMode' => $offer->getSeatMode(),
        ];
    }

    /**
     * Prefer course (embarkation) transport; fall back to offer.transport when unassigned.
     *
     * @return array{transport: ?AgencyTransport, vehicleUnassigned: bool, embarkationId: ?string}
     */
    public function resolveTransportForDate(AgencyOffer $offer, \DateTimeImmutable $travelDate): array
    {
        $embarkation = $this->embarkations->findOneForOfferOnDate($offer, $travelDate);
        if (null !== $embarkation && null !== $embarkation->getTransport()) {
            return [
                'transport' => $embarkation->getTransport(),
                'vehicleUnassigned' => false,
                'embarkationId' => $embarkation->getId(),
            ];
        }

        return [
            'transport' => $offer->getTransport(),
            'vehicleUnassigned' => true,
            'embarkationId' => $embarkation?->getId(),
        ];
    }

    /**
     * Active bookings + manual tickets (no booking) for offer+date — row count, not unique seats.
     */
    public function soldCount(
        AgencyOffer $offer,
        \DateTimeImmutable $travelDate,
        ?string $excludeBookingId = null,
    ): int {
        return $this->bookings->countActiveForOfferDate($offer, $travelDate, $excludeBookingId)
            + $this->tickets->countActiveManualForOfferDate($offer, $travelDate);
    }

    public function assertCapacityAvailable(
        AgencyOffer $offer,
        \DateTimeImmutable $travelDate,
        int $quantity = 1,
        ?string $excludeBookingId = null,
    ): void {
        $resolved = $this->resolveTransportForDate($offer, $travelDate);
        $transport = $resolved['transport'];
        if (null === $transport) {
            throw new UnprocessableEntityException('Offer has no transport.');
        }

        $sold = $this->soldCount($offer, $travelDate, $excludeBookingId);
        $capacity = (int) $transport->getCapacity();
        if ($sold + $quantity > $capacity) {
            throw new ConflictException(sprintf(
                'CAPACITY_FULL: No places left (%d/%d sold).',
                $sold,
                $capacity,
            ));
        }
    }

    /**
     * Resolve seat for sale: CAPACITY_ONLY skips layout validation; ASSIGNED_SEAT requires a seat map pick.
     */
    public function resolveSeatForSale(
        AgencyOffer $offer,
        \DateTimeImmutable $travelDate,
        ?string $seatNumber,
        ?string $excludeBookingId = null,
        ?string $excludeSeatNumber = null,
    ): string {
        if ($offer->isCapacityOnly()) {
            $this->assertCapacityAvailable($offer, $travelDate, 1, $excludeBookingId);
            $seat = $this->normalizeSeat($seatNumber);

            return '' !== $seat ? $seat : 'GA';
        }

        if (AgencyOffer::SEAT_NONE === $offer->getSeatMode()) {
            throw new UnprocessableEntityException('This offer does not support seat sales.');
        }

        return $this->assertSeatSelectable(
            $offer,
            $travelDate,
            $seatNumber,
            $excludeBookingId,
            $excludeSeatNumber,
        );
    }

    /**
     * Unique occupied seat labels — INTERCITY seat map only.
     *
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

        $resolved = $this->resolveTransportForDate($offer, $travelDate);
        $transport = $resolved['transport'];
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
        if ($offer->isCapacityOnly()) {
            $this->assertCapacityAvailable($offer, $travelDate, max(1, \count($seatNumbers)), $excludeBookingId);
            $resolved = [];
            foreach ($seatNumbers as $i => $seatNumber) {
                $seat = $this->normalizeSeat($seatNumber);
                $resolved[] = '' !== $seat ? $seat : sprintf('GA-%d', $i + 1);
            }

            return $resolved;
        }

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

        $resolved = $this->resolveTransportForDate($offer, $travelDate);
        $transport = $resolved['transport'];
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
