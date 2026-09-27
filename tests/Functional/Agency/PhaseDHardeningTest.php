<?php

namespace App\Tests\Functional\Agency;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTransport;
use App\Entity\SchoolContract;
use App\Entity\SchoolInvoice;

/**
 * Vague 11 Phase D / P2 — seat remap, school invoices, boarding anti-fraud.
 */
final class PhaseDHardeningTest extends AgencyApiTestCase
{
    public function testReassignCompatibleLayoutReturns200(): void
    {
        $ws = $this->createPartnerWorkspace('RemapOk', 8);
        $date = $this->travelDate('+8 days');

        $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Compat Pax',
            'passengerId' => 'CD-RMAP-1',
            'passengerPhone' => '+243890040001',
            'seatNumber' => '01A',
            'travelDate' => $date,
            'sendSms' => false,
        ], 201);

        $agency = $this->em->find(Agency::class, $ws['agency']->getId());
        self::assertInstanceOf(Agency::class, $agency);
        $otherBus = new AgencyTransport();
        $otherBus->setAgency($agency);
        $otherBus->setLabel('Bus B');
        $otherBus->setKind(AgencyTransport::KIND_BUS);
        $otherBus->setPlateNumber('BB'.strtoupper($this->suffix));
        $otherBus->setCapacity(8);
        $otherBus->setStatus(AgencyTransport::STATUS_ACTIVE);
        $this->em->persist($otherBus);
        $this->em->flush();

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course remap ok',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'departureDate' => $date,
            'departureTime' => '08:00',
        ], 201);

        $assigned = $this->api('POST', '/api/agency/embarkations/'.$embarkation['id'].'/assign', $ws['token'], [
            'transportId' => $otherBus->getId(),
        ], 200);

        self::assertSame('ASSIGNED', $assigned['status'] ?? null);
        self::assertSame($otherBus->getId(), $assigned['transport']['id'] ?? null);
        self::assertSame([], $assigned['remappedSeats'] ?? null);
    }

    public function testReassignIncompatibleWithoutForceReturns409(): void
    {
        $ws = $this->createPartnerWorkspace('RemapConflict', 8);
        $date = $this->travelDate('+9 days');

        // 01D exists on BUS (4 cols) but not on VAN (3 cols).
        $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Conflict Pax',
            'passengerId' => 'CD-RMAP-2',
            'passengerPhone' => '+243890040002',
            'seatNumber' => '01D',
            'travelDate' => $date,
            'sendSms' => false,
        ], 201);

        $van = $this->createVanTransport($ws['agency']->getId(), 8);

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course layout conflict',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'departureDate' => $date,
            'departureTime' => '09:00',
        ], 201);

        $conflict = $this->api('POST', '/api/agency/embarkations/'.$embarkation['id'].'/assign', $ws['token'], [
            'transportId' => $van->getId(),
            'force' => false,
        ], 409);

        self::assertStringContainsString('SEAT_LAYOUT_CONFLICT', (string) json_encode($conflict));
    }

    public function testReassignIncompatibleWithForceRemapsSeats(): void
    {
        $ws = $this->createPartnerWorkspace('RemapForce', 8);
        $date = $this->travelDate('+10 days');

        $ticketResult = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Remap Pax',
            'passengerId' => 'CD-RMAP-3',
            'passengerPhone' => '+243890040003',
            'seatNumber' => '01D',
            'travelDate' => $date,
            'sendSms' => false,
        ], 201);

        $ticketId = (string) ($ticketResult['ticket']['id'] ?? $ticketResult['id'] ?? '');
        self::assertNotSame('', $ticketId);

        $van = $this->createVanTransport($ws['agency']->getId(), 8);

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course force remap',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'departureDate' => $date,
            'departureTime' => '10:00',
        ], 201);

        $assigned = $this->api('POST', '/api/agency/embarkations/'.$embarkation['id'].'/assign', $ws['token'], [
            'transportId' => $van->getId(),
            'force' => true,
        ], 200);

        self::assertSame('ASSIGNED', $assigned['status'] ?? null);
        self::assertIsArray($assigned['remappedSeats'] ?? null);
        self::assertNotEmpty($assigned['remappedSeats']);
        $remap = $assigned['remappedSeats'][0];
        self::assertSame($ticketId, $remap['ticketId'] ?? null);
        self::assertSame('01D', $remap['from'] ?? null);
        self::assertNotSame('01D', $remap['to'] ?? null);
        self::assertNotEmpty($remap['to'] ?? null);

        $this->em->clear();
        /** @var AgencyTicket $ticket */
        $ticket = $this->em->getRepository(AgencyTicket::class)->find($ticketId);
        self::assertInstanceOf(AgencyTicket::class, $ticket);
        self::assertSame($remap['to'], $ticket->getSeatNumber());
    }

    public function testGenerateSchoolInvoiceAndMarkPaid(): void
    {
        $ws = $this->createPartnerWorkspace('InvSchool', 8);
        $schoolOffer = $this->api('POST', '/api/agency/offers', $ws['token'], [
            'label' => 'Offer scolaire invoice',
            'origin' => 'Quartier',
            'destination' => 'École',
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'ticketPrice' => 0,
            'currency' => 'CDF',
            'departureTime' => '06:00',
            'durationMinutes' => 30,
            'serviceType' => AgencyOffer::SERVICE_SCHOOL,
            'active' => true,
        ], 201);

        $contract = $this->api('POST', '/api/agency/school-contracts', $ws['token'], [
            'schoolName' => 'Lycée Invoice '.$this->suffix,
            'offer' => '/api/agency/offers/'.$schoolOffer['id'],
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'startDate' => '2026-09-01',
            'endDate' => '2027-06-30',
            'status' => SchoolContract::STATUS_ACTIVE,
            'monthlyFee' => 150000,
            'currency' => 'CDF',
        ], 201);

        $invoice = $this->api(
            'POST',
            '/api/agency/school-contracts/'.$contract['id'].'/invoices/generate',
            $ws['token'],
            ['periodYm' => '2026-10'],
            201,
        );

        self::assertStringStartsWith('IV', (string) ($invoice['id'] ?? ''));
        self::assertSame(SchoolInvoice::STATUS_ISSUED, $invoice['status'] ?? null);
        self::assertSame(150000, $invoice['amount'] ?? null);
        self::assertSame('2026-10', $invoice['periodYm'] ?? null);

        // Idempotent regenerate
        $again = $this->api(
            'POST',
            '/api/agency/school-contracts/'.$contract['id'].'/invoices/generate',
            $ws['token'],
            ['periodYm' => '2026-10'],
            201,
        );
        self::assertSame($invoice['id'], $again['id'] ?? null);

        $paid = $this->api(
            'POST',
            '/api/agency/school-invoices/'.$invoice['id'].'/mark-paid',
            $ws['token'],
            ['notes' => 'Virement reçu'],
            200,
        );
        self::assertSame(SchoolInvoice::STATUS_PAID, $paid['status'] ?? null);
        self::assertNotEmpty($paid['paidAt'] ?? null);

        $fetched = $this->api(
            'GET',
            '/api/agency/school-invoices/'.$invoice['id'],
            $ws['token'],
            null,
            200,
        );
        self::assertSame(SchoolInvoice::STATUS_PAID, $fetched['status'] ?? null);

        $list = $this->api(
            'GET',
            '/api/agency/school-invoices?status=PAID',
            $ws['token'],
            null,
            200,
        );
        $members = $this->collectionMembers($list);
        $ids = array_map(static fn (array $row): string => (string) ($row['id'] ?? ''), $members);
        self::assertContains($invoice['id'], $ids);
    }

    public function testDoubleValidateBoardingWithinCooldownReturnsError(): void
    {
        $ws = $this->createPartnerWorkspace('BoardCool', 8);
        $date = $this->travelDate('+11 days');

        $ticketResult = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Cool Pax',
            'passengerId' => 'CD-COOL-1',
            'passengerPhone' => '+243890040010',
            'seatNumber' => '01A',
            'travelDate' => $date,
            'sendSms' => false,
        ], 201);

        $ticketId = (string) ($ticketResult['ticket']['id'] ?? $ticketResult['id'] ?? '');
        self::assertNotSame('', $ticketId);

        $this->em->clear();
        /** @var AgencyTicket $ticket */
        $ticket = $this->em->getRepository(AgencyTicket::class)->find($ticketId);
        self::assertInstanceOf(AgencyTicket::class, $ticket);
        if (null === $ticket->getQrToken()) {
            $ticket->setQrToken(bin2hex(random_bytes(16)));
            $ticket->setQrTokenExpiresAt(new \DateTimeImmutable('+2 hours'));
            $this->em->flush();
        }
        $qrToken = (string) $ticket->getQrToken();

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course cooldown',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'departureDate' => $date,
            'departureTime' => '07:00',
        ], 201);

        $first = $this->api('POST', '/api/agency/trips/'.$embarkation['id'].'/validate-boarding', $ws['token'], [
            'token' => $qrToken,
        ], 200);
        self::assertSame(AgencyTicket::STATUS_BOARDED, $first['status'] ?? null);

        $this->em->clear();
        /** @var AgencyTicket $ticket */
        $ticket = $this->em->getRepository(AgencyTicket::class)->find($ticketId);
        self::assertInstanceOf(AgencyTicket::class, $ticket);
        $newToken = (string) $ticket->getQrToken();
        self::assertNotSame('', $newToken);

        $cooldown = $this->api('POST', '/api/agency/trips/'.$embarkation['id'].'/validate-boarding', $ws['token'], [
            'token' => $newToken,
        ], 409);

        self::assertStringContainsString('BOARDING_COOLDOWN', (string) json_encode($cooldown));
    }

    public function testValidateBoardingGeofenceWarningWhenCoordsProvided(): void
    {
        $ws = $this->createPartnerWorkspace('BoardGeo', 8);
        $date = $this->travelDate('+12 days');

        $ticketResult = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Geo Pax',
            'passengerId' => 'CD-GEO-1',
            'passengerPhone' => '+243890040011',
            'seatNumber' => '01B',
            'travelDate' => $date,
            'sendSms' => false,
        ], 201);

        $ticketId = (string) ($ticketResult['ticket']['id'] ?? $ticketResult['id'] ?? '');
        $this->em->clear();
        /** @var AgencyTicket $ticket */
        $ticket = $this->em->getRepository(AgencyTicket::class)->find($ticketId);
        self::assertInstanceOf(AgencyTicket::class, $ticket);
        if (null === $ticket->getQrToken()) {
            $ticket->setQrToken(bin2hex(random_bytes(16)));
            $ticket->setQrTokenExpiresAt(new \DateTimeImmutable('+2 hours'));
            $this->em->flush();
        }
        $qrToken = (string) $ticket->getQrToken();

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course geo',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'departureDate' => $date,
            'departureTime' => '07:30',
        ], 201);

        $result = $this->api('POST', '/api/agency/trips/'.$embarkation['id'].'/validate-boarding', $ws['token'], [
            'token' => $qrToken,
            'lat' => -4.3276,
            'lng' => 15.3136,
        ], 200);

        self::assertTrue($result['geofenceWarning'] ?? false);
        self::assertContains('GEOFENCE_UNCHECKED', $result['warnings'] ?? []);
    }

    private function createVanTransport(string $agencyId, int $capacity): AgencyTransport
    {
        $agency = $this->em->find(Agency::class, $agencyId);
        self::assertInstanceOf(Agency::class, $agency);
        $van = new AgencyTransport();
        $van->setAgency($agency);
        $van->setLabel('Van remap');
        $van->setKind(AgencyTransport::KIND_VAN);
        $van->setPlateNumber('VN'.strtoupper($this->suffix));
        $van->setCapacity($capacity);
        $van->setStatus(AgencyTransport::STATUS_ACTIVE);
        $this->em->persist($van);
        $this->em->flush();

        return $van;
    }
}
