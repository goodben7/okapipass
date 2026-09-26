<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Entity\Agency;
use App\Entity\AgencyDepartureChecklist;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyFleetIncident;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTransport;
use App\Entity\AgencyWorkOrder;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyDepartureChecklistRepository;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyFleetIncidentRepository;
use App\Repository\AgencyFuelLogRepository;
use App\Repository\AgencyTicketRepository;
use App\Repository\AgencyTransportRepository;
use App\Repository\AgencyWorkOrderRepository;
use App\Service\Agency\AgencyContext;

final class AgencyFleetReportManager
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AgencyEmbarkationRepository $embarkations,
        private AgencyDepartureChecklistRepository $checklists,
        private AgencyTicketRepository $tickets,
        private AgencyFuelLogRepository $fuelLogs,
        private AgencyWorkOrderRepository $workOrders,
        private AgencyTransportRepository $transports,
        private AgencyFleetIncidentRepository $incidents,
    ) {
    }

    public function requireAgency(): Agency
    {
        $this->agencyContext->requirePermission(AgencyPermission::FLEET_READ);

        return $this->agencyContext->requireAgency();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function driverMissions(Agency $agency, \DateTimeImmutable $date, ?string $driverId = null): array
    {
        $qb = $this->embarkations->createQueryBuilder('e')
            ->leftJoin('e.driver', 'd')->addSelect('d')
            ->leftJoin('e.transport', 't')->addSelect('t')
            ->leftJoin('e.offer', 'o')->addSelect('o')
            ->andWhere('e.agency = :agency')
            ->andWhere('e.departureDate = :date')
            ->setParameter('agency', $agency)
            ->setParameter('date', $date->setTime(0, 0))
            ->orderBy('e.departureTime', 'ASC');

        if (null !== $driverId && '' !== trim($driverId)) {
            $qb->andWhere('d.id = :driverId')->setParameter('driverId', trim($driverId));
        }

        /** @var list<AgencyEmbarkation> $rows */
        $rows = $qb->getQuery()->getResult();
        $missions = [];
        foreach ($rows as $embarkation) {
            $passengerCount = 0;
            foreach ($embarkation->getTickets() as $ticket) {
                if (AgencyTicket::STATUS_CANCELLED !== $ticket->getStatus()) {
                    ++$passengerCount;
                }
            }

            $checklist = $this->checklists->findOneBy([
                'agency' => $agency,
                'transport' => $embarkation->getTransport(),
                'travelDate' => $date->setTime(0, 0),
            ], ['createdAt' => 'DESC']);

            $missions[] = [
                'embarkationId' => $embarkation->getId(),
                'driverId' => $embarkation->getDriver()?->getId(),
                'driverName' => $embarkation->getDriver()?->getFullName(),
                'transportId' => $embarkation->getTransport()?->getId(),
                'transportLabel' => $embarkation->getTransport()?->getLabel(),
                'offerId' => $embarkation->getOffer()?->getId(),
                'offerLabel' => $embarkation->getOffer()?->getLabel(),
                'passengerCount' => $passengerCount,
                'checklistStatus' => $checklist instanceof AgencyDepartureChecklist
                    ? $checklist->getStatus()
                    : null,
                'departureTime' => $embarkation->getDepartureTime(),
                'status' => $embarkation->getStatus(),
            ];
        }

        return $missions;
    }

    /**
     * @return array{transportId: string, from: string, to: string, fuelCost: int, maintenanceCost: int, deltaOdometer: int, costPerKm: float|null}
     */
    public function costPerKm(
        Agency $agency,
        string $transportId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $transport = $this->transports->find($transportId);
        if (!$transport instanceof AgencyTransport || $transport->getAgency()?->getId() !== $agency->getId()) {
            throw new UnprocessableEntityException('Transport not found.');
        }

        $fuelRows = $this->fuelLogs->createQueryBuilder('f')
            ->andWhere('f.agency = :agency')
            ->andWhere('f.transport = :transport')
            ->andWhere('f.fueledAt >= :from')
            ->andWhere('f.fueledAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('transport', $transport)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->orderBy('f.odometerKm', 'ASC')
            ->getQuery()
            ->getResult();

        $fuelCost = 0;
        $liters = 0;
        $odos = [];
        foreach ($fuelRows as $row) {
            $fuelCost += (int) $row->getAmount();
            $liters += (int) ($row->getLiters() ?? 0);
            if (null !== $row->getOdometerKm()) {
                $odos[] = (int) $row->getOdometerKm();
            }
        }

        $woRows = $this->workOrders->createQueryBuilder('w')
            ->andWhere('w.agency = :agency')
            ->andWhere('w.transport = :transport')
            ->andWhere('w.createdAt >= :from')
            ->andWhere('w.createdAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('transport', $transport)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->getQuery()
            ->getResult();

        $maintenanceCost = 0;
        foreach ($woRows as $wo) {
            /** @var AgencyWorkOrder $wo */
            $maintenanceCost += $wo->getPartsCost() + $wo->getLaborCost();
        }

        $delta = 0;
        if (\count($odos) >= 2) {
            $delta = max(0, max($odos) - min($odos));
        }
        $totalCost = $fuelCost + $maintenanceCost;
        $costPerKm = $delta > 0 ? round($totalCost / $delta, 2) : null;
        $litersPer100Km = $delta > 0 ? round(100 * $liters / $delta, 2) : 0.0;

        return [
            'transportId' => (string) $transport->getId(),
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'fuelCost' => $fuelCost,
            'maintenanceCost' => $maintenanceCost,
            'deltaOdometer' => $delta,
            'costPerKm' => $costPerKm,
            'litersPer100Km' => $litersPer100Km,
        ];
    }

    /**
     * @return array{from: string, to: string, rows: list<array<string, mixed>>}
     */
    public function punctuality(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<AgencyEmbarkation> $rows */
        $rows = $this->embarkations->createQueryBuilder('e')
            ->leftJoin('e.offer', 'o')->addSelect('o')
            ->leftJoin('e.transport', 't')->addSelect('t')
            ->andWhere('e.agency = :agency')
            ->andWhere('e.departureDate >= :from')
            ->andWhere('e.departureDate <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->orderBy('e.departureDate', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $embarkation) {
            $planned = (string) ($embarkation->getDepartureTime() ?? '00:00');
            $actual = $embarkation->getDepartedAt();
            $deltaMinutes = null;
            $onTime = null;
            if ($actual instanceof \DateTimeImmutable) {
                $plannedDt = \DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    ($embarkation->getDepartureDate()?->format('Y-m-d') ?? $actual->format('Y-m-d')).' '.$planned,
                );
                if ($plannedDt instanceof \DateTimeImmutable) {
                    $deltaMinutes = (int) round(($actual->getTimestamp() - $plannedDt->getTimestamp()) / 60);
                    $onTime = $deltaMinutes <= 15;
                }
            }
            $out[] = [
                'embarkationId' => $embarkation->getId(),
                'offerId' => $embarkation->getOffer()?->getId(),
                'transportId' => $embarkation->getTransport()?->getId(),
                'departureDate' => $embarkation->getDepartureDate()?->format('Y-m-d'),
                'plannedDepartureTime' => $planned,
                'actualDepartedAt' => $actual?->format(\DateTimeInterface::ATOM),
                'delayMinutes' => $deltaMinutes,
                'onTime' => $onTime,
            ];
        }

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'rows' => $out,
        ];
    }

    /**
     * @return array{from: string, to: string, drivers: list<array<string, mixed>>}
     */
    public function driversRanking(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<AgencyEmbarkation> $rows */
        $rows = $this->embarkations->createQueryBuilder('e')
            ->leftJoin('e.driver', 'd')->addSelect('d')
            ->andWhere('e.agency = :agency')
            ->andWhere('e.departureDate >= :from')
            ->andWhere('e.departureDate <= :to')
            ->andWhere('e.driver IS NOT NULL')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->getQuery()
            ->getResult();

        $byDriver = [];
        foreach ($rows as $embarkation) {
            $driverId = (string) $embarkation->getDriver()?->getId();
            if (!isset($byDriver[$driverId])) {
                $byDriver[$driverId] = [
                    'driverId' => $driverId,
                    'driverName' => $embarkation->getDriver()?->getFullName(),
                    'trips' => 0,
                    'onTime' => 0,
                    'timed' => 0,
                ];
            }
            ++$byDriver[$driverId]['trips'];
            $actual = $embarkation->getDepartedAt();
            $planned = (string) ($embarkation->getDepartureTime() ?? '00:00');
            if ($actual instanceof \DateTimeImmutable) {
                $plannedDt = \DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    ($embarkation->getDepartureDate()?->format('Y-m-d') ?? $actual->format('Y-m-d')).' '.$planned,
                );
                if ($plannedDt instanceof \DateTimeImmutable) {
                    ++$byDriver[$driverId]['timed'];
                    $delay = (int) round(($actual->getTimestamp() - $plannedDt->getTimestamp()) / 60);
                    if ($delay <= 15) {
                        ++$byDriver[$driverId]['onTime'];
                    }
                }
            }
        }

        $incidentRows = $this->incidents->createQueryBuilder('i')
            ->select('IDENTITY(i.driver) AS driverId, COUNT(i.id) AS cnt')
            ->andWhere('i.agency = :agency')
            ->andWhere('i.occurredAt >= :from')
            ->andWhere('i.occurredAt <= :to')
            ->andWhere('i.driver IS NOT NULL')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->groupBy('i.driver')
            ->getQuery()
            ->getResult();
        $incidentMap = [];
        foreach ($incidentRows as $row) {
            $incidentMap[(string) $row['driverId']] = (int) $row['cnt'];
        }

        $drivers = [];
        foreach ($byDriver as $driverId => $row) {
            $onTimePercent = $row['timed'] > 0 ? round(100 * $row['onTime'] / $row['timed'], 1) : null;
            $drivers[] = [
                'driverId' => $row['driverId'],
                'driverName' => $row['driverName'],
                'trips' => $row['trips'],
                'onTimePercent' => $onTimePercent,
                'incidentCount' => $incidentMap[$driverId] ?? 0,
            ];
        }
        usort($drivers, static fn (array $a, array $b): int => $b['trips'] <=> $a['trips']);

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'drivers' => $drivers,
        ];
    }

    /**
     * Best-effort cost aggregation by corridor (offer origin→destination).
     *
     * @return array{from: string, to: string, corridors: list<array<string, mixed>>}
     */
    public function costByCorridor(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $fuelByTransport = $this->fuelLogs->createQueryBuilder('f')
            ->select('IDENTITY(f.transport) AS transportId, SUM(f.amount) AS fuelCost, SUM(f.liters) AS liters')
            ->andWhere('f.agency = :agency')
            ->andWhere('f.fueledAt >= :from')
            ->andWhere('f.fueledAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->groupBy('f.transport')
            ->getQuery()
            ->getResult();

        $fuelMap = [];
        foreach ($fuelByTransport as $row) {
            $fuelMap[(string) $row['transportId']] = [
                'fuelCost' => (int) $row['fuelCost'],
                'liters' => (int) $row['liters'],
            ];
        }

        /** @var list<AgencyEmbarkation> $embarkations */
        $embarkations = $this->embarkations->createQueryBuilder('e')
            ->leftJoin('e.offer', 'o')->addSelect('o')
            ->leftJoin('e.transport', 't')->addSelect('t')
            ->andWhere('e.agency = :agency')
            ->andWhere('e.departureDate >= :from')
            ->andWhere('e.departureDate <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->getQuery()
            ->getResult();

        $corridors = [];
        $transportAssigned = [];
        foreach ($embarkations as $embarkation) {
            $offer = $embarkation->getOffer();
            $transportId = (string) ($embarkation->getTransport()?->getId() ?? '');
            $key = sprintf('%s→%s', $offer?->getOrigin() ?? '?', $offer?->getDestination() ?? '?');
            if (!isset($corridors[$key])) {
                $corridors[$key] = [
                    'corridor' => $key,
                    'origin' => $offer?->getOrigin(),
                    'destination' => $offer?->getDestination(),
                    'trips' => 0,
                    'fuelCost' => 0,
                    'liters' => 0,
                ];
            }
            ++$corridors[$key]['trips'];
            if ('' !== $transportId && !isset($transportAssigned[$transportId])) {
                $transportAssigned[$transportId] = $key;
                $corridors[$key]['fuelCost'] += $fuelMap[$transportId]['fuelCost'] ?? 0;
                $corridors[$key]['liters'] += $fuelMap[$transportId]['liters'] ?? 0;
            }
        }

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'corridors' => array_values($corridors),
        ];
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     availabilityPercent: float,
     *     occupancyPercent: float|null,
     *     incidentCount: int,
     *     maintenanceCost: int
     * }
     */
    public function summary(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $total = $this->transports->countByAgency($agency);
        $active = $this->transports->countByAgencyAndStatus($agency, AgencyTransport::STATUS_ACTIVE);
        $availability = $total > 0 ? round(100 * $active / $total, 2) : 0.0;

        $boarded = (int) $this->tickets->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.travelDate >= :from')
            ->andWhere('t.travelDate <= :to')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->setParameter('statuses', [AgencyTicket::STATUS_BOARDED, AgencyTicket::STATUS_USED])
            ->getQuery()
            ->getSingleScalarResult();

        $capacity = (int) $this->transports->createQueryBuilder('tr')
            ->select('COALESCE(SUM(tr.capacity), 0)')
            ->andWhere('tr.agency = :agency')
            ->setParameter('agency', $agency)
            ->getQuery()
            ->getSingleScalarResult();

        $occupancy = $capacity > 0 ? round(100 * min($boarded, $capacity) / $capacity, 2) : null;

        $incidentCount = (int) $this->incidents->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.agency = :agency')
            ->andWhere('i.occurredAt >= :from')
            ->andWhere('i.occurredAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->getQuery()
            ->getSingleScalarResult();

        $maintenanceCost = (int) $this->workOrders->createQueryBuilder('w')
            ->select('COALESCE(SUM(w.partsCost + w.laborCost), 0)')
            ->andWhere('w.agency = :agency')
            ->andWhere('w.createdAt >= :from')
            ->andWhere('w.createdAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'availabilityPercent' => $availability,
            'occupancyPercent' => $occupancy,
            'incidentCount' => $incidentCount,
            'maintenanceCost' => $maintenanceCost,
        ];
    }
}
