<?php

namespace App\Tests\Functional\PublicAgency;

use App\Entity\AgencyBooking;
use App\Tests\Functional\Agency\AgencyApiTestCase;

final class PublicAgencyFoundationTest extends AgencyApiTestCase
{
    public function testPublicHealthEndpointIsAccessibleWithoutAuth(): void
    {
        $this->client->request('GET', '/api/public/agency/health');
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('ok', $body['status'] ?? null);
        self::assertSame('public-agency', $body['service'] ?? null);
    }

    public function testPartnerCanEnableOnlineSalesOnOffer(): void
    {
        $ws = $this->createPartnerWorkspace('OnlineSales');

        $patched = $this->api(
            'PATCH',
            '/api/agency/offers/'.$ws['offer']->getId(),
            $ws['token'],
            ['onlineSales' => true, 'bookingHoldMinutes' => 20],
            200,
        );

        self::assertTrue($patched['onlineSales'] ?? false);
        self::assertSame(20, $patched['bookingHoldMinutes'] ?? null);
    }

    public function testDeskBookingHasChannelAndPaymentDefaults(): void
    {
        $ws = $this->createPartnerWorkspace('DeskChannel');
        $travelDate = (new \DateTimeImmutable('+3 days'))->format('Y-m-d');

        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => $ws['offer']->getId(),
            'passengerName' => 'Test User',
            'passengerId' => 'ID-001',
            'passengerPhone' => '+243812345678',
            'seatNumber' => '01A',
            'travelDate' => $travelDate,
        ], 201);

        $payload = $booking['booking'] ?? $booking;
        self::assertSame(AgencyBooking::CHANNEL_DESK, $payload['channel'] ?? null);
        self::assertSame(AgencyBooking::PAYMENT_STATUS_UNPAID, $payload['paymentStatus'] ?? null);
    }

    public function testIssueTicketStillWorksAfterIssuanceRefactor(): void
    {
        $ws = $this->createPartnerWorkspace('IssuanceRefactor');
        $travelDate = (new \DateTimeImmutable('+4 days'))->format('Y-m-d');

        $created = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => $ws['offer']->getId(),
            'passengerName' => 'Jean Test',
            'passengerId' => 'ID-002',
            'passengerPhone' => '+243898765432',
            'seatNumber' => '01B',
            'travelDate' => $travelDate,
        ], 201);

        $bookingId = $this->extractId($created);
        self::assertNotEmpty($bookingId);

        $ticket = $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);
        self::assertStringStartsWith('VP-', (string) ($ticket['reference'] ?? ''));
    }
}
