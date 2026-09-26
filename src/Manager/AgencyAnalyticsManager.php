<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Entity\Agency;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;

final class AgencyAnalyticsManager
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AgencyTicketRepository $tickets,
    ) {
    }

    public function requireAgency(): Agency
    {
        return $this->agencyContext->requireAgency();
    }

    /**
     * @return array{from: string, to: string, corridors: list<array{origin: string, destination: string, ticketsSold: int, capacityEstimate: int, occupancyPercent: float}>}
     */
    public function corridors(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $this->agencyContext->requirePermission(AgencyPermission::ACCOUNTING_READ);
        $this->agencyContext->assertOwns($agency);

        $rows = $this->tickets->corridorOccupancy($agency, $from, $to);
        $corridors = [];
        foreach ($rows as $row) {
            $sold = (int) $row['ticketsSold'];
            $capacity = max(1, (int) $row['capacityEstimate']);
            $corridors[] = [
                'origin' => (string) $row['origin'],
                'destination' => (string) $row['destination'],
                'ticketsSold' => $sold,
                'capacityEstimate' => $capacity,
                'occupancyPercent' => round(100 * $sold / $capacity, 1),
            ];
        }

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'corridors' => $corridors,
        ];
    }

    /**
     * @return array{offerId: string, date: string, ticketsSold: int, capacity: int, occupancyPercent: float, projectedFillPercent: float}
     */
    public function fillForecast(Agency $agency, string $offerId, \DateTimeImmutable $date): array
    {
        $this->agencyContext->requirePermission(AgencyPermission::FLEET_READ);
        $this->agencyContext->assertOwns($agency);

        $stats = $this->tickets->offerDayOccupancy($agency, $offerId, $date);
        $sold = (int) $stats['ticketsSold'];
        $capacity = max(1, (int) $stats['capacity']);
        $occupancy = 100 * $sold / $capacity;

        $weekday = (int) $date->format('w');
        $historical = $this->tickets->sameWeekdayOccupancyAvg($agency, $offerId, $date, 8);
        $projected = null !== $historical
            ? min(100.0, round($historical, 1))
            : min(100.0, round($occupancy, 1));

        return [
            'offerId' => $offerId,
            'date' => $date->format('Y-m-d'),
            'ticketsSold' => $sold,
            'capacity' => $capacity,
            'occupancyPercent' => round($occupancy, 1),
            'projectedFillPercent' => $projected,
        ];
    }
}
