<?php

namespace App\Tests\Functional\PublicAgency;

use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Manager\PublicAgencyPaymentManager;
use App\Tests\Functional\Agency\AgencyApiTestCase;

final class PublicAgencyPaymentTest extends AgencyApiTestCase
{
    public function testInitiateMobileMoneyPayment(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '01A');

        $body = $this->publicPost(
            '/api/public/agency/bookings/'.$created['publicToken'].'/pay',
            ['method' => AgencyPayment::METHOD_MOBILE_MONEY],
        );

        self::assertSame(AgencyPayment::STATUS_PENDING, $body['paymentStatus'] ?? null);
        self::assertSame(AgencyPayment::METHOD_MOBILE_MONEY, $body['paymentMethod'] ?? null);
        self::assertNotEmpty($body['paymentId'] ?? null);
        self::assertNotEmpty($body['providerTransactionId'] ?? null);
        self::assertSame(AgencyBooking::PAYMENT_STATUS_PENDING, $body['bookingPaymentStatus'] ?? null);
        self::assertGreaterThan(0, $body['amount'] ?? 0);
    }

    public function testInitiateCardPaymentReturnsFormUrl(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '01B');

        $body = $this->publicPost(
            '/api/public/agency/bookings/'.$created['publicToken'].'/pay',
            ['method' => AgencyPayment::METHOD_CARD],
        );

        self::assertSame(AgencyPayment::METHOD_CARD, $body['paymentMethod'] ?? null);
        self::assertStringContainsString('/api/public/agency/payments/', (string) ($body['cardFormUrl'] ?? ''));
    }

    public function testWebhookFulfillmentIssuesTicket(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '02A');

        $pay = $this->publicPost(
            '/api/public/agency/bookings/'.$created['publicToken'].'/pay',
            ['method' => AgencyPayment::METHOD_MOBILE_MONEY],
        );

        $payment = $this->em->find(AgencyPayment::class, $pay['paymentId']);
        self::assertInstanceOf(AgencyPayment::class, $payment);

        /** @var PublicAgencyPaymentManager $manager */
        $manager = static::getContainer()->get(PublicAgencyPaymentManager::class);
        $manager->fulfillSuccessfulPayment($payment);
        $this->em->refresh($payment);

        self::assertSame(AgencyPayment::STATUS_PAID, $payment->getStatus());
        self::assertNotNull($payment->getTicket());

        $booking = $this->em->find(AgencyBooking::class, $created['bookingId']);
        self::assertInstanceOf(AgencyBooking::class, $booking);
        self::assertSame(AgencyBooking::STATUS_CONFIRMED, $booking->getStatus());
        self::assertSame(AgencyBooking::PAYMENT_STATUS_PAID, $booking->getPaymentStatus());
    }

    public function testGetTicketAfterPayment(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '02B');

        $pay = $this->publicPost(
            '/api/public/agency/bookings/'.$created['publicToken'].'/pay',
            ['method' => AgencyPayment::METHOD_MOBILE_MONEY],
        );

        $payment = $this->em->find(AgencyPayment::class, $pay['paymentId']);
        self::assertInstanceOf(AgencyPayment::class, $payment);

        static::getContainer()->get(PublicAgencyPaymentManager::class)->fulfillSuccessfulPayment($payment);

        $this->publicGet('/api/public/agency/bookings/'.$created['publicToken'].'/ticket');
        $body = $this->decodeJsonResponse();

        self::assertNotEmpty($body['reference'] ?? null);
        self::assertSame('02B', $body['seatNumber'] ?? null);
        self::assertNotEmpty($body['qrPayload'] ?? null);
        self::assertSame(AgencyTicket::STATUS_ISSUED, $body['status'] ?? null);
    }

    public function testGetTicketBeforePaymentReturns404(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '02C');

        $this->publicGet('/api/public/agency/bookings/'.$created['publicToken'].'/ticket', 404);
    }

    public function testPayAlreadyPaidBookingReturns409(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '02D');

        $pay = $this->publicPost(
            '/api/public/agency/bookings/'.$created['publicToken'].'/pay',
            ['method' => AgencyPayment::METHOD_MOBILE_MONEY],
        );

        $payment = $this->em->find(AgencyPayment::class, $pay['paymentId']);
        self::assertInstanceOf(AgencyPayment::class, $payment);
        static::getContainer()->get(PublicAgencyPaymentManager::class)->fulfillSuccessfulPayment($payment);

        $this->publicPost(
            '/api/public/agency/bookings/'.$created['publicToken'].'/pay',
            ['method' => AgencyPayment::METHOD_MOBILE_MONEY],
            409,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createPublicBooking(array $ws, string $seat): array
    {
        return $this->publicPost('/api/public/agency/bookings', [
            'offerId' => $ws['offer']->getId(),
            'travelDate' => $this->travelDate('+7 days'),
            'seatNumber' => $seat,
            'passengerName' => 'Jean Kabongo',
            'passengerId' => 'ID-'.str_replace('0', '', $seat),
            'passengerPhone' => '+243812345678',
        ], 201);
    }

    /**
     * @return array{offer: AgencyOffer, agency: \App\Entity\Agency, token: string}
     */
    private function createOnlineOfferWorkspace(): array
    {
        $ws = $this->createPartnerWorkspace('PublicPayment');
        $offer = $this->em->find(AgencyOffer::class, $ws['offer']->getId());
        self::assertInstanceOf(AgencyOffer::class, $offer);
        $offer->setOnlineSales(true);
        $offer->setBookingHoldMinutes(15);
        $this->em->flush();

        return $ws;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function publicPost(string $uri, ?array $body, int $expectedStatus = 200): array
    {
        $this->client->request(
            'POST',
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            $expectedStatus,
            $this->client->getResponse()->getStatusCode(),
            sprintf('POST %s: %s', $uri, $this->client->getResponse()->getContent()),
        );

        return $this->decodeJsonResponse();
    }

    private function publicGet(string $uri, int $expectedStatus = 200): void
    {
        $this->client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(
            $expectedStatus,
            $this->client->getResponse()->getStatusCode(),
            sprintf('GET %s: %s', $uri, $this->client->getResponse()->getContent()),
        );
    }

    /** @return array<string, mixed> */
    private function decodeJsonResponse(): array
    {
        $decoded = json_decode($this->client->getResponse()->getContent() ?: '{}', true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
