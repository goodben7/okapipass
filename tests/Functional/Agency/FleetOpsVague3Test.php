<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyDepartureChecklist;
use App\Entity\AgencyTransport;
use App\Entity\AgencyWorkOrder;

final class FleetOpsVague3Test extends AgencyApiTestCase
{
    public function testCreateFuelLog(): void
    {
        $ws = $this->createPartnerWorkspace('FuelV3');

        $created = $this->api('POST', '/api/agency/fleet/fuel-logs', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'liters' => 120,
            'amount' => 360000,
            'currency' => 'CDF',
            'odometerKm' => 84500,
            'notes' => 'Plein route Matadi',
        ], 201);

        self::assertNotEmpty($created['id'] ?? null);
        self::assertStringStartsWith('FL', (string) $created['id']);
        self::assertSame(120, $created['liters'] ?? null);
        self::assertSame(360000, $created['amount'] ?? null);
    }

    public function testDepartureChecklistSubmit(): void
    {
        $ws = $this->createPartnerWorkspace('ChecklistV3');
        $travelDate = $this->travelDate('+2 days');

        $created = $this->api('POST', '/api/agency/fleet/departures/checklists', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'travelDate' => $travelDate,
            'odometerKm' => 120500,
            'fuelLevelPercent' => 85,
            'vehicleOk' => true,
            'notes' => 'Pneus OK',
        ], 201);

        self::assertSame(AgencyDepartureChecklist::STATUS_DRAFT, $created['status'] ?? null);
        $checklistId = $created['id'] ?? null;
        self::assertNotNull($checklistId);

        $submitted = $this->api(
            'POST',
            '/api/agency/fleet/departures/checklists/'.$checklistId.'/submit',
            $ws['token'],
            null,
        );
        self::assertSame(AgencyDepartureChecklist::STATUS_SUBMITTED, $submitted['status'] ?? null);
        self::assertNotEmpty($submitted['submittedAt'] ?? null);
    }

    public function testWorkOrderImmobilizeSetsTransportMaintenance(): void
    {
        $ws = $this->createPartnerWorkspace('WorkOrderV3');

        $created = $this->api('POST', '/api/agency/fleet/maintenance/work-orders', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'title' => 'Remplacement embrayage',
            'description' => 'Immobilisation atelier',
            'partsCost' => 180000,
            'laborCost' => 90000,
            'immobilize' => true,
            'vendorName' => 'Garage Nord',
        ], 201);

        self::assertSame(AgencyWorkOrder::STATUS_OPEN, $created['status'] ?? null);
        self::assertTrue($created['immobilize'] ?? false);

        $this->em->clear();
        $transport = $this->em->find(AgencyTransport::class, $ws['transport']->getId());
        self::assertInstanceOf(AgencyTransport::class, $transport);
        self::assertSame(AgencyTransport::STATUS_MAINTENANCE, $transport->getStatus());

        $completed = $this->api(
            'POST',
            '/api/agency/fleet/maintenance/work-orders/'.$created['id'].'/complete',
            $ws['token'],
            null,
        );
        self::assertSame(AgencyWorkOrder::STATUS_DONE, $completed['status'] ?? null);

        $this->em->clear();
        $transport = $this->em->find(AgencyTransport::class, $ws['transport']->getId());
        self::assertInstanceOf(AgencyTransport::class, $transport);
        self::assertSame(AgencyTransport::STATUS_ACTIVE, $transport->getStatus());
    }
}
