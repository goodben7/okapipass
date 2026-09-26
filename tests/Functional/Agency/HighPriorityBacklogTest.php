<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AccountingJournal;
use App\Entity\AgencyFleetIncident;
use App\Entity\AgencyParcel;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTicketCancelRequest;

final class HighPriorityBacklogTest extends AgencyApiTestCase
{
    public function testCancelRequestApproveFlow(): void
    {
        $ws = $this->createPartnerWorkspace('CancelReq');
        $travelDate = $this->travelDate('+5 days');

        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Client Annulation',
            'passengerId' => 'CD-CAN-1',
            'passengerPhone' => '+243830099887',
            'seatNumber' => '02A',
            'travelDate' => $travelDate,
            'status' => 'CONFIRMED',
            'sendSms' => false,
        ], 201);
        $bookingId = $this->extractId($booking);
        self::assertNotNull($bookingId);

        $issued = $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);
        $ticketId = $issued['id'] ?? $issued['ticket']['id'] ?? null;
        if (null === $ticketId) {
            $ticket = $this->em->getRepository(AgencyTicket::class)->findOneBy(['passengerPhone' => '+243830099887']);
            self::assertInstanceOf(AgencyTicket::class, $ticket);
            $ticketId = $ticket->getId();
        }
        self::assertNotNull($ticketId);

        $request = $this->api('POST', '/api/agency/tickets/'.$ticketId.'/cancel-requests', $ws['token'], [
            'reason' => 'Erreur de saisie guichet',
        ], 201);
        self::assertSame(AgencyTicketCancelRequest::STATUS_PENDING, $request['status'] ?? null);
        self::assertStringStartsWith('CR', (string) ($request['id'] ?? ''));
        $requestId = $request['id'] ?? null;
        self::assertNotNull($requestId);

        $approved = $this->api('POST', '/api/agency/ticket-cancel-requests/'.$requestId.'/approve', $ws['token'], null, 200);
        self::assertSame(AgencyTicketCancelRequest::STATUS_APPROVED, $approved['status'] ?? null);

        $ticket = $this->em->getRepository(AgencyTicket::class)->find($ticketId);
        self::assertInstanceOf(AgencyTicket::class, $ticket);
        self::assertSame(AgencyTicket::STATUS_CANCELLED, $ticket->getStatus());
    }

    public function testParcelCreate(): void
    {
        $ws = $this->createPartnerWorkspace('Parcel');

        $parcel = $this->api('POST', '/api/agency/parcels', $ws['token'], [
            'senderName' => 'Jean Expéditeur',
            'senderPhone' => '+243810011223',
            'recipientName' => 'Marie Destinataire',
            'recipientPhone' => '+243820033445',
            'weightKg' => 12.5,
            'fee' => 5000,
            'currency' => 'CDF',
            'travelDate' => $this->travelDate('+2 days'),
            'notes' => 'Fragile',
        ], 201);

        self::assertStringStartsWith('PL', (string) ($parcel['id'] ?? ''));
        self::assertSame(AgencyParcel::STATUS_BOOKED, $parcel['status'] ?? null);
        self::assertMatchesRegularExpression('/^PL-[A-Z0-9]{4}$/', (string) ($parcel['trackingCode'] ?? ''));
    }

    public function testFleetIncidentCreate(): void
    {
        $ws = $this->createPartnerWorkspace('Incident');

        $incident = $this->api('POST', '/api/agency/fleet/incidents', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => AgencyFleetIncident::TYPE_BREAKDOWN,
            'severity' => AgencyFleetIncident::SEVERITY_HIGH,
            'lat' => -4.321,
            'lng' => 15.312,
            'notes' => 'Panne moteur route Matadi',
            'occurredAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], 201);

        self::assertStringStartsWith('FI', (string) ($incident['id'] ?? ''));
        self::assertSame(AgencyFleetIncident::STATUS_OPEN, $incident['status'] ?? null);
        self::assertSame(AgencyFleetIncident::TYPE_BREAKDOWN, $incident['type'] ?? null);
    }

    public function testReconciliationEndpointSmoke(): void
    {
        $ws = $this->createPartnerWorkspace('Recon');
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $payment = new AgencyPayment();
        $payment->setAgency($ws['agency']);
        $payment->setReference('ABP-RECON-'.strtoupper($this->suffix));
        $payment->setAmount(15000);
        $payment->setCurrency('CDF');
        $payment->setMethod(AgencyPayment::METHOD_CASH);
        $payment->setStatus(AgencyPayment::STATUS_PAID);
        $payment->setChannel(AgencyPayment::CHANNEL_POS);
        $payment->setProvider(AgencyPayment::PROVIDER_FLEXPAY);
        $payment->setPaidAt(new \DateTimeImmutable());
        $this->em->persist($payment);
        $this->em->flush();

        /** @var \App\Manager\AccountingAgencyManager $accounting */
        $accounting = static::getContainer()->get(\App\Manager\AccountingAgencyManager::class);
        $accounting->recordFromAgencyPayment($payment);

        $journal = $this->em->getRepository(AccountingJournal::class)->findOneBy([
            'sourceType' => AccountingJournal::SOURCE_AGENCY_PAYMENT,
            'sourceId' => $payment->getId(),
        ]);
        self::assertInstanceOf(AccountingJournal::class, $journal);

        $result = $this->api(
            'GET',
            '/api/agency/accounting/reconciliation?from='.$today.'&to='.$today,
            $ws['token'],
            null,
            200,
        );

        self::assertSame($today, $result['from'] ?? null);
        self::assertSame($today, $result['to'] ?? null);
        self::assertGreaterThanOrEqual(1, $result['paidPaymentsCount'] ?? 0);
        self::assertGreaterThanOrEqual(15000, $result['paidAmount'] ?? 0);
        self::assertGreaterThanOrEqual(1, $result['journalCreditCount'] ?? 0);
        self::assertIsArray($result['unmatchedPaymentIds'] ?? null);
        self::assertIsArray($result['unmatchedJournalSourceIds'] ?? null);
    }
}
