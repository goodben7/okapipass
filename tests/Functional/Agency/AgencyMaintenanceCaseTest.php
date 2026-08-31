<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyMaintenanceCase;
use App\Entity\AgencyTransport;

final class AgencyMaintenanceCaseTest extends AgencyApiTestCase
{
    public function testCreateMaintenanceCaseSetsTransportToMaintenance(): void
    {
        $ws = $this->createPartnerWorkspace('MaintCreate');

        $created = $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_REPAIR,
            'title' => 'Freins avant',
            'description' => 'Bruit au freinage',
            'odometerKm' => 120000,
            'estimatedCost' => 250000,
            'vendorName' => 'Garage Central',
        ], 201);

        self::assertSame(AgencyMaintenanceCase::STATUS_OPEN, $created['status'] ?? null);
        self::assertSame('Freins avant', $created['title'] ?? null);

        $this->em->clear();
        $transport = $this->em->find(AgencyTransport::class, $ws['transport']->getId());
        self::assertInstanceOf(AgencyTransport::class, $transport);
        self::assertSame(AgencyTransport::STATUS_MAINTENANCE, $transport->getStatus());
    }

    public function testOpenMaintenanceCaseBlocksBooking(): void
    {
        $ws = $this->createPartnerWorkspace('MaintBlock');
        $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_REPAIR,
            'title' => 'Moteur',
        ], 201);

        $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Blocked Passenger',
            'passengerId' => 'CD-700001',
            'passengerPhone' => '+243870000001',
            'seatNumber' => '01A',
            'travelDate' => $this->travelDate(),
            'sendSms' => false,
        ], 422);
    }

    public function testCompleteMaintenanceCaseRestoresTransportAndSales(): void
    {
        $ws = $this->createPartnerWorkspace('MaintDone');
        $created = $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_INSPECTION,
            'title' => 'Controle technique',
        ], 201);

        $completed = $this->api(
            'POST',
            '/api/agency/maintenance-cases/'.$created['id'].'/complete',
            $ws['token'],
            ['actualCost' => 150000],
        );
        self::assertSame(AgencyMaintenanceCase::STATUS_DONE, $completed['status'] ?? null);
        self::assertSame(150000, $completed['actualCost'] ?? null);

        $this->em->clear();
        $transport = $this->em->find(AgencyTransport::class, $ws['transport']->getId());
        self::assertInstanceOf(AgencyTransport::class, $transport);
        self::assertSame(AgencyTransport::STATUS_ACTIVE, $transport->getStatus());

        $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Allowed Passenger',
            'passengerId' => 'CD-700002',
            'passengerPhone' => '+243870000002',
            'seatNumber' => '01B',
            'travelDate' => $this->travelDate(),
            'sendSms' => false,
        ], 201);
    }

    public function testStartMaintenanceCaseWorkflow(): void
    {
        $ws = $this->createPartnerWorkspace('MaintStart');
        $created = $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_PREVENTIVE,
            'title' => 'Vidange',
        ], 201);

        $started = $this->api('POST', '/api/agency/maintenance-cases/'.$created['id'].'/start', $ws['token'], null);
        self::assertSame(AgencyMaintenanceCase::STATUS_IN_PROGRESS, $started['status'] ?? null);
        self::assertNotEmpty($started['startedAt'] ?? null);
    }

    public function testCancelMaintenanceCaseRestoresTransport(): void
    {
        $ws = $this->createPartnerWorkspace('MaintCancel');
        $created = $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_OTHER,
            'title' => 'Fausse alerte',
        ], 201);

        $cancelled = $this->api('POST', '/api/agency/maintenance-cases/'.$created['id'].'/cancel', $ws['token'], null);
        self::assertSame(AgencyMaintenanceCase::STATUS_CANCELLED, $cancelled['status'] ?? null);

        $this->em->clear();
        $transport = $this->em->find(AgencyTransport::class, $ws['transport']->getId());
        self::assertInstanceOf(AgencyTransport::class, $transport);
        self::assertSame(AgencyTransport::STATUS_ACTIVE, $transport->getStatus());
    }

    public function testFilterMaintenanceCasesByTransport(): void
    {
        $ws = $this->createPartnerWorkspace('MaintFilter');
        $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_REPAIR,
            'title' => 'Pneus',
        ], 201);

        $filtered = $this->api(
            'GET',
            '/api/agency/maintenance-cases?transport.id='.$ws['transport']->getId(),
            $ws['token'],
            null,
            200,
        );
        $members = $this->collectionMembers($filtered);
        self::assertCount(1, $members);
        self::assertSame('Pneus', $members[0]['title'] ?? null);
    }

    public function testPartnerCannotReadOtherAgencyMaintenanceCase(): void
    {
        $a = $this->createPartnerWorkspace('MaintA');
        $b = $this->createPartnerWorkspace('MaintB');

        $created = $this->api('POST', '/api/agency/maintenance-cases', $a['token'], [
            'transport' => '/api/agency/transports/'.$a['transport']->getId(),
            'type' => AgencyMaintenanceCase::TYPE_REPAIR,
            'title' => 'Private case',
        ], 201);

        $this->api('GET', '/api/agency/maintenance-cases/'.$created['id'], $a['token'], null, 200);
        $this->api('GET', '/api/agency/maintenance-cases/'.$created['id'], $b['token'], null, 404);
    }

    public function testSecondOpenCaseKeepsTransportBlockedUntilAllClosed(): void
    {
        $ws = $this->createPartnerWorkspace('MaintMulti');
        $transportId = $ws['transport']->getId();

        $first = $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$transportId,
            'type' => AgencyMaintenanceCase::TYPE_REPAIR,
            'title' => 'Case one',
        ], 201);

        $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$transportId,
            'type' => AgencyMaintenanceCase::TYPE_INSPECTION,
            'title' => 'Case two',
        ], 201);

        $this->api('POST', '/api/agency/maintenance-cases/'.$first['id'].'/complete', $ws['token'], [], 200);

        $this->em->clear();
        $transport = $this->em->find(AgencyTransport::class, $transportId);
        self::assertInstanceOf(AgencyTransport::class, $transport);
        self::assertSame(AgencyTransport::STATUS_MAINTENANCE, $transport->getStatus());
    }
}
