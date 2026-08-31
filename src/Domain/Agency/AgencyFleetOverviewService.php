<?php

namespace App\Domain\Agency;

use App\Entity\Agency;
use App\Entity\AgencyDriver;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyMaintenanceCase;
use App\Entity\AgencyRentalContract;
use App\Entity\AgencyTransport;
use App\Repository\AgencyDriverRepository;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyMaintenanceCaseRepository;
use App\Repository\AgencyRentalContractRepository;
use App\Repository\AgencyTransportRepository;

final class AgencyFleetOverviewService
{
    public function __construct(
        private AgencyTransportRepository $transports,
        private AgencyDriverRepository $drivers,
        private AgencyMaintenanceCaseRepository $maintenanceCases,
        private AgencyRentalContractRepository $rentals,
        private AgencyEmbarkationRepository $embarkations,
    ) {
    }

    /**
     * @return array{
     *     kpis: array{
     *         totalTransports: int,
     *         activeTransports: int,
     *         maintenanceTransports: int,
     *         inactiveTransports: int,
     *         activeDrivers: int,
     *         driversOnDutyToday: int,
     *         driversWithExpiringLicense: int,
     *         openMaintenanceCases: int,
     *         activeRentals: int,
     *         maintenanceCostThisMonth: int
     *     },
     *     recentMaintenanceCases: list<array<string, mixed>>,
     *     activeRentals: list<array<string, mixed>>,
     *     expiringLicenses: list<array<string, mixed>>
     * }
     */
    public function buildOverview(Agency $agency): array
    {
        $today = new \DateTimeImmutable('today');
        $monthStart = $today->modify('first day of this month')->setTime(0, 0);
        $licenseAlertUntil = $today->modify('+30 days');

        return [
            'kpis' => [
                'totalTransports' => $this->transports->countByAgency($agency),
                'activeTransports' => $this->transports->countByAgencyAndStatus($agency, AgencyTransport::STATUS_ACTIVE),
                'maintenanceTransports' => $this->transports->countByAgencyAndStatus($agency, AgencyTransport::STATUS_MAINTENANCE),
                'inactiveTransports' => $this->transports->countByAgencyAndStatus($agency, AgencyTransport::STATUS_INACTIVE),
                'activeDrivers' => $this->drivers->countActiveByAgency($agency),
                'driversOnDutyToday' => $this->drivers->countOnDutyToday($agency, $today),
                'driversWithExpiringLicense' => $this->drivers->countExpiringLicenses($agency, $today, $licenseAlertUntil),
                'openMaintenanceCases' => $this->maintenanceCases->countBlockingByAgency($agency),
                'activeRentals' => $this->rentals->countBlockingByAgency($agency),
                'maintenanceCostThisMonth' => $this->maintenanceCases->sumCompletedCostSince($agency, $monthStart),
            ],
            'recentMaintenanceCases' => array_map(
                fn (AgencyMaintenanceCase $case): array => $this->serializeMaintenanceCase($case),
                $this->maintenanceCases->findRecentOpenByAgency($agency, 5),
            ),
            'activeRentals' => array_map(
                fn (AgencyRentalContract $contract): array => $this->serializeRentalContract($contract),
                $this->rentals->findBlockingByAgency($agency, 5),
            ),
            'expiringLicenses' => array_map(
                fn (AgencyDriver $driver): array => $this->serializeDriverLicenseAlert($driver),
                $this->drivers->findExpiringLicenses($agency, $today, $licenseAlertUntil, 10),
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function driverAssignments(AgencyDriver $driver, int $limit = 20): array
    {
        $rows = [];
        foreach ($this->embarkations->findRecentByDriver($driver, $limit) as $embarkation) {
            $rows[] = $this->serializeAssignment($embarkation);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMaintenanceCase(AgencyMaintenanceCase $case): array
    {
        return [
            'id' => $case->getId(),
            'transportId' => $case->getTransport()?->getId(),
            'transportLabel' => $case->getTransport()?->getLabel(),
            'type' => $case->getType(),
            'status' => $case->getStatus(),
            'title' => $case->getTitle(),
            'reportedAt' => $case->getReportedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRentalContract(AgencyRentalContract $contract): array
    {
        return [
            'id' => $contract->getId(),
            'transportId' => $contract->getTransport()?->getId(),
            'transportLabel' => $contract->getTransport()?->getLabel(),
            'clientName' => $contract->getClientName(),
            'status' => $contract->getStatus(),
            'startAt' => $contract->getStartAt()?->format(\DateTimeInterface::ATOM),
            'endAt' => $contract->getEndAt()?->format(\DateTimeInterface::ATOM),
            'driverId' => $contract->getDriver()?->getId(),
            'driverName' => $contract->getDriver()?->getFullName(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDriverLicenseAlert(AgencyDriver $driver): array
    {
        return [
            'id' => $driver->getId(),
            'fullName' => $driver->getFullName(),
            'licenseNumber' => $driver->getLicenseNumber(),
            'licenseExpiresAt' => $driver->getLicenseExpiresAt()?->format('Y-m-d'),
            'status' => $driver->getStatus(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAssignment(AgencyEmbarkation $embarkation): array
    {
        return [
            'embarkationId' => $embarkation->getId(),
            'label' => $embarkation->getLabel(),
            'status' => $embarkation->getStatus(),
            'departureDate' => $embarkation->getDepartureDate()?->format('Y-m-d'),
            'departureTime' => $embarkation->getDepartureTime(),
            'offerId' => $embarkation->getOffer()?->getId(),
            'offerLabel' => $embarkation->getOffer()?->getLabel(),
            'transportId' => $embarkation->getTransport()?->getId(),
            'transportLabel' => $embarkation->getTransport()?->getLabel(),
        ];
    }
}
