<?php

namespace App\Tests\Functional\Agency;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use App\Entity\SchoolContract;

/**
 * Vague 9 P0 — assign bus ↔ course (Embarkation = Trip).
 */
final class TripAssignFleetTest extends AgencyApiTestCase
{
    public function testAssignTransportCreatesTripAssignment(): void
    {
        $ws = $this->createPartnerWorkspace('AssignOk', 8);
        $date = $this->travelDate('+4 days');

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course sans bus',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'departureDate' => $date,
            'departureTime' => '08:00',
        ], 201);

        self::assertStringStartsWith('AE', (string) ($embarkation['id'] ?? ''));
        self::assertTrue(
            null === ($embarkation['transport'] ?? null) || '' === ($embarkation['transport'] ?? null),
            'Transport should be unassigned on create'
        );

        $assigned = $this->api('POST', '/api/agency/embarkations/'.$embarkation['id'].'/assign', $ws['token'], [
            'transportId' => $ws['transport']->getId(),
        ], 200);

        self::assertSame($embarkation['id'], $assigned['tripId'] ?? null);
        self::assertSame('ASSIGNED', $assigned['status'] ?? null);
        self::assertSame($ws['transport']->getId(), $assigned['transport']['id'] ?? null);
        self::assertNotEmpty($assigned['assignmentId'] ?? null);
        self::assertStringStartsWith('TA', (string) $assigned['assignmentId']);

        $audit = $this->api(
            'GET',
            '/api/agency/trip-assignments?embarkation.id='.$embarkation['id'],
            $ws['token'],
            null,
            200,
        );
        $members = $this->collectionMembers($audit);
        self::assertNotEmpty($members);
        self::assertStringStartsWith('TA', (string) ($members[0]['id'] ?? ''));
    }

    public function testAssignSameTransportOverlappingReturns409(): void
    {
        $ws = $this->createPartnerWorkspace('AssignOverlap', 12);
        $date = $this->travelDate('+5 days');
        $transportIri = '/api/agency/transports/'.$ws['transport']->getId();

        $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course A',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => $transportIri,
            'departureDate' => $date,
            'departureTime' => '06:00',
        ], 201);

        $b = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course B',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'departureDate' => $date,
            'departureTime' => '07:00',
        ], 201);

        $conflict = $this->api('POST', '/api/agency/embarkations/'.$b['id'].'/assign', $ws['token'], [
            'transportId' => $ws['transport']->getId(),
        ], 409);

        self::assertStringContainsString('TRANSPORT_SCHEDULE_CONFLICT', (string) json_encode($conflict));
    }

    public function testAssignCapacityBelowSoldReturns409(): void
    {
        $ws = $this->createPartnerWorkspace('AssignCap', 8);
        $date = $this->travelDate('+6 days');

        // Sell 2 seats on offer (capacity 8)
        for ($i = 1; $i <= 2; ++$i) {
            $this->api('POST', '/api/agency/tickets', $ws['token'], [
                'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
                'passengerName' => 'Pax '.$i,
                'passengerId' => 'CD-CAP-'.$i,
                'passengerPhone' => '+24389000'.sprintf('%04d', $i),
                'seatNumber' => sprintf('0%dA', $i),
                'travelDate' => $date,
                'sendSms' => false,
            ], 201);
        }

        $agency = $this->em->find(Agency::class, $ws['agency']->getId());
        self::assertInstanceOf(Agency::class, $agency);
        $small = new AgencyTransport();
        $small->setAgency($agency);
        $small->setLabel('Mini 1 place');
        $small->setKind(AgencyTransport::KIND_VAN);
        $small->setPlateNumber('SM'.strtoupper($this->suffix));
        $small->setCapacity(1);
        $small->setStatus(AgencyTransport::STATUS_ACTIVE);
        $this->em->persist($small);
        $this->em->flush();

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course capacité',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'departureDate' => $date,
            'departureTime' => '09:00',
        ], 201);

        $conflict = $this->api('POST', '/api/agency/embarkations/'.$embarkation['id'].'/assign', $ws['token'], [
            'transportId' => $small->getId(),
        ], 409);

        self::assertStringContainsString('CAPACITY_BELOW_SOLD', (string) json_encode($conflict));
    }

    public function testSeatAvailabilityUsesAssignedTransport(): void
    {
        $ws = $this->createPartnerWorkspace('AssignSeats', 8);
        $date = $this->travelDate('+7 days');

        $agency = $this->em->find(Agency::class, $ws['agency']->getId());
        self::assertInstanceOf(Agency::class, $agency);
        $coaster = new AgencyTransport();
        $coaster->setAgency($agency);
        $coaster->setLabel('Coaster assigné');
        $coaster->setKind(AgencyTransport::KIND_COASTER);
        $coaster->setPlateNumber('CO'.strtoupper($this->suffix));
        $coaster->setCapacity(14);
        $coaster->setStatus(AgencyTransport::STATUS_ACTIVE);
        $this->em->persist($coaster);
        $this->em->flush();

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course sièges',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'departureDate' => $date,
            'departureTime' => '10:00',
        ], 201);

        $before = $this->api(
            'GET',
            '/api/agency/offers/'.$ws['offer']->getId().'/seat-availability?travelDate='.$date,
            $ws['token'],
            null,
            200,
        );
        self::assertTrue($before['vehicleUnassigned'] ?? false);
        self::assertSame(8, $before['capacity'] ?? null);

        $this->api('POST', '/api/agency/embarkations/'.$embarkation['id'].'/assign', $ws['token'], [
            'transportId' => $coaster->getId(),
        ], 200);

        $after = $this->api(
            'GET',
            '/api/agency/offers/'.$ws['offer']->getId().'/seat-availability?travelDate='.$date,
            $ws['token'],
            null,
            200,
        );
        self::assertFalse($after['vehicleUnassigned'] ?? true);
        self::assertSame($coaster->getId(), $after['transportId'] ?? null);
        self::assertSame($embarkation['id'], $after['embarkationId'] ?? null);
        self::assertSame(14, $after['capacity'] ?? null);
        self::assertSame(AgencyTransport::KIND_COASTER, $after['layout']['kind'] ?? null);
    }

    public function testSchoolDepartureAssignTransport(): void
    {
        $ws = $this->createPartnerWorkspace('AssignSchool', 20);
        $schoolOffer = $this->api('POST', '/api/agency/offers', $ws['token'], [
            'label' => 'Navette scolaire assign',
            'origin' => 'Quartier',
            'destination' => 'École',
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'ticketPrice' => 0,
            'currency' => 'CDF',
            'departureTime' => '06:00',
            'durationMinutes' => 40,
            'serviceType' => AgencyOffer::SERVICE_SCHOOL,
            'active' => true,
        ], 201);

        $contract = $this->api('POST', '/api/agency/school-contracts', $ws['token'], [
            'schoolName' => 'Lycée Assign '.$this->suffix,
            'offer' => '/api/agency/offers/'.$schoolOffer['id'],
            'startDate' => '2026-09-01',
            'endDate' => '2027-06-30',
            'status' => SchoolContract::STATUS_ACTIVE,
            'monthlyFee' => 0,
            'stops' => [
                ['code' => 'A', 'label' => 'Arrêt A', 'order' => 1],
                ['code' => 'B', 'label' => 'École', 'order' => 2],
            ],
        ], 201);

        $date = $this->travelDate('+3 days');
        $departure = $this->api('POST', '/api/agency/school/departures', $ws['token'], [
            'contractId' => $contract['id'],
            'date' => $date,
        ], 201);

        $agency = $this->em->find(Agency::class, $ws['agency']->getId());
        self::assertInstanceOf(Agency::class, $agency);
        $alt = new AgencyTransport();
        $alt->setAgency($agency);
        $alt->setLabel('Bus scolaire 2');
        $alt->setKind(AgencyTransport::KIND_BUS);
        $alt->setPlateNumber('SC'.strtoupper($this->suffix));
        $alt->setCapacity(30);
        $alt->setStatus(AgencyTransport::STATUS_ACTIVE);
        $this->em->persist($alt);
        $this->em->flush();

        $assigned = $this->api(
            'PATCH',
            '/api/agency/school/departures/'.$departure['id'].'/assign-transport',
            $ws['token'],
            ['transportId' => $alt->getId()],
            200,
        );

        self::assertSame($departure['id'], $assigned['embarkationId'] ?? null);
        self::assertSame($alt->getId(), $assigned['transport']['id'] ?? null);
        self::assertStringStartsWith('TA', (string) ($assigned['assignmentId'] ?? ''));
    }

    public function testTripsAliasAssignWorks(): void
    {
        $ws = $this->createPartnerWorkspace('AssignAlias', 8);
        $date = $this->travelDate('+8 days');

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Alias trip',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'departureDate' => $date,
            'departureTime' => '11:00',
        ], 201);

        $assigned = $this->api('POST', '/api/agency/trips/'.$embarkation['id'].'/assign', $ws['token'], [
            'transportId' => $ws['transport']->getId(),
        ], 200);

        self::assertSame($embarkation['id'], $assigned['tripId'] ?? null);
        self::assertSame($ws['transport']->getId(), $assigned['transport']['id'] ?? null);

        $fleet = $this->api(
            'GET',
            '/api/agency/fleet/trips?date='.$date.'&unassignedOnly=false',
            $ws['token'],
            null,
            200,
        );
        self::assertIsArray($fleet['trips'] ?? null);
        $ids = array_map(static fn (array $t): string => (string) ($t['id'] ?? ''), $fleet['trips']);
        self::assertContains($embarkation['id'], $ids);
    }
}
