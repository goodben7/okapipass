<?php

namespace App\Tests\Functional\PublicAgency;

use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;
use App\Tests\Functional\Agency\AgencyApiTestCase;

final class PublicAgencyBookingTest extends AgencyApiTestCase
{
    public function testCreateOnlineBookingReturnsTokenAndQuote(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $travelDate = $this->travelDate('+5 days');

        $body = $this->publicPost('/api/public/agency/bookings', [
            'offerId' => $ws['offer']->getId(),
            'travelDate' => $travelDate,
            'seatNumber' => '01A',
            'passengerName' => 'Jean Kabongo',
            'passengerId' => 'ID-100',
            'passengerPhone' => '+243812345678',
        ], 201);

        self::assertSame(AgencyBooking::STATUS_PENDING, $body['status'] ?? null);
        self::assertSame(AgencyBooking::PAYMENT_STATUS_UNPAID, $body['paymentStatus'] ?? null);
        self::assertSame(AgencyBooking::CHANNEL_ONLINE, $this->bookingChannel($body['bookingId']));
        self::assertNotEmpty($body['publicToken'] ?? null);
        self::assertNotEmpty($body['expiresAt'] ?? null);
        self::assertSame(85000, $body['quote']['ticketPrice'] ?? null);
        self::assertGreaterThan(0, $body['quote']['passPrice'] ?? 0);
        self::assertSame($ws['offer']->getId(), $body['offer']['id'] ?? null);
    }

    public function testGetBookingByPublicToken(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '01B');

        $this->publicGet('/api/public/agency/bookings/'.$created['publicToken']);
        $body = $this->decodeJsonResponse();

        self::assertSame($created['bookingId'], $body['bookingId'] ?? null);
        self::assertSame('01B', $body['seatNumber'] ?? null);
    }

    public function testCancelUnpaidBooking(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '01C');

        $this->publicPost('/api/public/agency/bookings/'.$created['publicToken'].'/cancel', null, 200);
        $body = $this->decodeJsonResponse();

        self::assertSame(AgencyBooking::STATUS_CANCELLED, $body['status'] ?? null);
        self::assertSame(AgencyBooking::PAYMENT_STATUS_FAILED, $body['paymentStatus'] ?? null);
    }

    public function testDuplicateSeatReturns409(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $travelDate = $this->travelDate('+6 days');
        $payload = [
            'offerId' => $ws['offer']->getId(),
            'travelDate' => $travelDate,
            'seatNumber' => '02A',
            'passengerName' => 'Passenger One',
            'passengerId' => 'ID-201',
            'passengerPhone' => '+243810000001',
        ];

        $this->publicPost('/api/public/agency/bookings', $payload, 201);
        $this->publicPost('/api/public/agency/bookings', array_merge($payload, [
            'passengerName' => 'Passenger Two',
            'passengerId' => 'ID-202',
            'passengerPhone' => '+243810000002',
        ]), 409);
    }

    public function testExpireCommandCancelsPastHold(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createPublicBooking($ws, '02D');

        $booking = $this->em->find(AgencyBooking::class, $created['bookingId']);
        self::assertInstanceOf(AgencyBooking::class, $booking);
        $booking->setExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->em->flush();

        self::assertSame(0, $this->getExpireCommandExitCode());

        $this->em->refresh($booking);
        self::assertSame(AgencyBooking::STATUS_CANCELLED, $booking->getStatus());
    }

    public function testCreateFailsWhenOfferNotOnline(): void
    {
        $ws = $this->createPartnerWorkspace('OfflineBooking');

        $this->publicPost('/api/public/agency/bookings', [
            'offerId' => $ws['offer']->getId(),
            'travelDate' => $this->travelDate('+4 days'),
            'seatNumber' => '01A',
            'passengerName' => 'Jean Kabongo',
            'passengerId' => 'ID-300',
            'passengerPhone' => '+243812345678',
        ], 404);
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
        $ws = $this->createPartnerWorkspace('PublicBooking');
        $offer = $this->em->find(AgencyOffer::class, $ws['offer']->getId());
        self::assertInstanceOf(AgencyOffer::class, $offer);
        $offer->setOnlineSales(true);
        $offer->setBookingHoldMinutes(15);
        $this->em->flush();

        return $ws;
    }

    private function bookingChannel(?string $bookingId): ?string
    {
        if (null === $bookingId || '' === $bookingId) {
            return null;
        }

        $booking = $this->em->find(AgencyBooking::class, $bookingId);

        return $booking?->getChannel();
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

    private function getExpireCommandExitCode(): int
    {
        return self::getContainer()->get(\App\Command\ExpirePublicAgencyBookingsCommand::class)->run(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        );
    }
}
