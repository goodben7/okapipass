<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyDriver;
use App\Entity\AgencyMaintenanceCase;
use App\Entity\AgencyRentalContract;
use App\Entity\AgencyTransport;

final class AgencyFleetOverviewTest extends AgencyApiTestCase
{
    public function testFleetOverviewAggregatesKpis(): void
    {
        $ws = $this->createPartnerWorkspace('FleetOverview');
        $travel = $this->travelDate('+5 days');

        $this->api('POST', '/api/agency/drivers', $ws['token'], [
            'fullName' => 'Overview Driver',
            'phone' => '+243810000101',
            'licenseNumber' => 'OVR-'.strtoupper($this->suffix),
            'licenseExpiresAt' => (new \DateTimeImmutable('+20 days'))->format('Y-m-d'),
        ], 201);

        $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_REPAIR,
            'title' => 'Overview maintenance',
        ], 201);

        $rental = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'clientName' => 'Overview Client',
            'clientPhone' => '+243810000102',
            'startAt' => $travel.'T08:00:00',
            'endAt' => (new \DateTimeImmutable($travel))->modify('+1 day')->format('Y-m-d').'T18:00:00',
            'dailyRate' => 100000,
            'totalAmount' => 200000,
        ], 201);
        $this->api('POST', '/api/agency/rental-contracts/'.$rental['id'].'/confirm', $ws['token']);

        $overview = $this->api('GET', '/api/agency/fleet/overview', $ws['token']);

        self::assertSame('overview', $overview['id'] ?? null);
        self::assertSame(1, $overview['kpis']['activeDrivers'] ?? 0);
        self::assertSame(1, $overview['kpis']['openMaintenanceCases'] ?? 0);
        self::assertSame(1, $overview['kpis']['activeRentals'] ?? 0);
        self::assertSame(1, $overview['kpis']['maintenanceTransports'] ?? 0);
        self::assertSame(1, $overview['kpis']['driversWithExpiringLicense'] ?? 0);
        self::assertNotEmpty($overview['recentMaintenanceCases'] ?? []);
        self::assertNotEmpty($overview['activeRentals'] ?? []);
        self::assertNotEmpty($overview['expiringLicenses'] ?? []);
    }

    public function testDashboardIncludesFleetKpis(): void
    {
        $ws = $this->createPartnerWorkspace('FleetDash');

        $dashboard = $this->api('GET', '/api/agency/dashboard', $ws['token']);

        self::assertArrayHasKey('fleet', $dashboard);
        self::assertSame(1, $dashboard['fleet']['totalTransports'] ?? 0);
        self::assertSame(1, $dashboard['fleet']['activeTransports'] ?? 0);
    }

    public function testDriverAssignmentsListsEmbarkations(): void
    {
        $ws = $this->createPartnerWorkspace('FleetAssign');
        $driver = $this->api('POST', '/api/agency/drivers', $ws['token'], [
            'fullName' => 'Assignment Driver',
            'phone' => '+243810000103',
            'licenseNumber' => 'ASG-'.strtoupper($this->suffix),
            'status' => AgencyDriver::STATUS_ACTIVE,
        ], 201);

        $departureDate = $this->travelDate('+6 days');
        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Fleet assignment trip',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'driver' => '/api/agency/drivers/'.$driver['id'],
            'departureDate' => $departureDate,
            'departureTime' => '06:00',
        ], 201);

        $assignments = $this->api(
            'GET',
            '/api/agency/drivers/'.$driver['id'].'/assignments',
            $ws['token'],
        );

        self::assertSame($driver['id'], $assignments['driverId'] ?? null);
        self::assertSame('Assignment Driver', $assignments['driverName'] ?? null);
        self::assertCount(1, $assignments['assignments'] ?? []);
        self::assertSame($embarkation['id'], $assignments['assignments'][0]['embarkationId'] ?? null);
        self::assertSame($departureDate, $assignments['assignments'][0]['departureDate'] ?? null);
    }

    public function testPartnerCannotReadOtherAgencyFleetOverview(): void
    {
        $a = $this->createPartnerWorkspace('FleetA');
        $b = $this->createPartnerWorkspace('FleetB');

        $overviewA = $this->api('GET', '/api/agency/fleet/overview', $a['token']);
        self::assertSame(1, $overviewA['kpis']['totalTransports'] ?? 0);

        $overviewB = $this->api('GET', '/api/agency/fleet/overview', $b['token']);
        self::assertSame(1, $overviewB['kpis']['totalTransports'] ?? 0);
        self::assertNotSame($overviewA['kpis']['activeDrivers'] ?? 0, -1);
    }

    public function testAgencyMeIncludesFleetPermissions(): void
    {
        $ws = $this->createPartnerWorkspace('FleetPerm');
        $me = $this->api('GET', '/api/agency/me', $ws['token']);

        self::assertContains('fleet:read', $me['permissions'] ?? []);
        self::assertContains('fleet:write', $me['permissions'] ?? []);
        self::assertContains('driver:write', $me['permissions'] ?? []);
        self::assertContains('maintenance:write', $me['permissions'] ?? []);
        self::assertContains('rental:write', $me['permissions'] ?? []);
    }

    public function testCompletedMaintenanceCostAppearsInOverview(): void
    {
        $ws = $this->createPartnerWorkspace('FleetCost');
        $created = $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_INSPECTION,
            'title' => 'Cost tracking',
        ], 201);

        $this->api('POST', '/api/agency/maintenance-cases/'.$created['id'].'/complete', $ws['token'], [
            'actualCost' => 175000,
        ]);

        $this->em->clear();
        $transport = $this->em->find(AgencyTransport::class, $ws['transport']->getId());
        self::assertInstanceOf(AgencyTransport::class, $transport);
        self::assertSame(AgencyTransport::STATUS_ACTIVE, $transport->getStatus());

        $overview = $this->api('GET', '/api/agency/fleet/overview', $ws['token']);
        self::assertSame(0, $overview['kpis']['openMaintenanceCases'] ?? -1);
        self::assertGreaterThanOrEqual(175000, $overview['kpis']['maintenanceCostThisMonth'] ?? 0);
    }
}
