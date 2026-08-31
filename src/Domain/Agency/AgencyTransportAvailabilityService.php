<?php

namespace App\Domain\Agency;

use App\Entity\AgencyTransport;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyMaintenanceCaseRepository;
use App\Repository\AgencyRentalContractRepository;

final class AgencyTransportAvailabilityService
{
    public function __construct(
        private AgencyMaintenanceCaseRepository $maintenanceCases,
        private AgencyRentalContractRepository $rentals,
    ) {
    }

    public function assertAvailableForTravelDate(AgencyTransport $transport, \DateTimeImmutable $travelDate): void
    {
        if (!$transport->isActiveForSale()) {
            throw new UnprocessableEntityException('Transport is not ACTIVE — sales are blocked.');
        }

        if ($this->maintenanceCases->countBlockingByTransport($transport) > 0) {
            throw new UnprocessableEntityException('Transport is under maintenance — sales are blocked.');
        }

        if ($this->rentals->hasBlockingRentalOnTravelDate($transport, $travelDate)) {
            throw new UnprocessableEntityException('Transport is rented on this travel date.');
        }
    }

    /**
     * @return array{
     *     transportId: string,
     *     from: string,
     *     to: string,
     *     days: list<array{date: string, available: bool, reason: string|null}>
     * }
     */
    public function buildCalendar(
        AgencyTransport $transport,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        if ($to < $from) {
            throw new UnprocessableEntityException('Parameter "to" must be on or after "from".');
        }

        $days = [];
        $cursor = $from;
        $globalReason = $this->globalUnavailableReason($transport);

        while ($cursor <= $to && \count($days) < 90) {
            $reason = $globalReason;
            if (null === $reason && $this->rentals->hasBlockingRentalOnTravelDate($transport, $cursor)) {
                $reason = 'RENTAL';
            }

            $days[] = [
                'date' => $cursor->format('Y-m-d'),
                'available' => null === $reason,
                'reason' => $reason,
            ];

            $cursor = $cursor->modify('+1 day');
        }

        return [
            'transportId' => (string) $transport->getId(),
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'days' => $days,
        ];
    }

    private function globalUnavailableReason(AgencyTransport $transport): ?string
    {
        if (AgencyTransport::STATUS_INACTIVE === $transport->getStatus()) {
            return 'INACTIVE';
        }

        if (!$transport->isActiveForSale() || $this->maintenanceCases->countBlockingByTransport($transport) > 0) {
            return 'MAINTENANCE';
        }

        return null;
    }
}
