<?php

namespace App\Tests\Functional\PublicAgency;

use App\Entity\AgencyBookingGroup;
use App\Entity\AgencyOffer;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Manager\PublicAgencyPaymentManager;
use App\Tests\Functional\Agency\AgencyApiTestCase;

final class PublicAgencyGroupBookingTest extends AgencyApiTestCase
{
    public function testCreateGroupBookingWithOptionalPassengerFields(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $body = $this->publicPost('/api/public/agency/booking-groups', [
            'offerId' => $ws['offer']->getId(),
            'travelDate' => $this->travelDate('+7 days'),
            'groupName' => 'Famille Kabongo',
            'contactPhone' => '+243812345678',
            'passengers' => [
                ['seatNumber' => '01A'],
                ['seatNumber' => '01B', 'passengerName' => 'Marie'],
            ],
        ], 201);

        self::assertSame('Famille Kabongo', $body['groupName'] ?? null);
        self::assertCount(2, $body['passengers'] ?? []);
        self::assertTrue(
            !isset($body['passengers'][0]['passengerName']) || null === $body['passengers'][0]['passengerName'],
        );
        self::assertSame('Marie', $body['passengers'][1]['passengerName'] ?? null);
    }

    public function testGroupPaymentIssuesSingleGroupedTicket(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createGroupBooking($ws, ['02B', '02C']);

        $pay = $this->publicPost(
            '/api/public/agency/booking-groups/'.$created['publicToken'].'/pay',
            [
                'method' => AgencyPayment::METHOD_MOBILE_MONEY,
                'payerPhone' => '+243811111111',
            ],
        );

        $payment = $this->em->find(AgencyPayment::class, $pay['paymentId']);
        self::assertInstanceOf(AgencyPayment::class, $payment);

        $ticket = static::getContainer()->get(PublicAgencyPaymentManager::class)->fulfillSuccessfulGroupPayment($payment);
        self::assertTrue($ticket->isGroupTicket());
        self::assertSame(['02B', '02C'], $ticket->getGroupSeatList());
        self::assertNotEmpty($ticket->getReference());

        $this->publicGet('/api/public/agency/booking-groups/'.$created['publicToken'].'/ticket');
        $body = $this->decodeJsonResponse();
        self::assertTrue($body['isGroupTicket'] ?? false);
        self::assertSame(['02B', '02C'], $body['groupSeats'] ?? null);
        self::assertSame(2, $body['passengerCount'] ?? null);
    }

    public function testDownloadGroupedTicketPdf(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createGroupBooking($ws, ['01C', '01D']);

        $pay = $this->publicPost(
            '/api/public/agency/booking-groups/'.$created['publicToken'].'/pay',
            ['method' => AgencyPayment::METHOD_MOBILE_MONEY],
        );

        $payment = $this->em->find(AgencyPayment::class, $pay['paymentId']);
        self::assertInstanceOf(AgencyPayment::class, $payment);
        static::getContainer()->get(PublicAgencyPaymentManager::class)->fulfillSuccessfulGroupPayment($payment);

        $this->client->request(
            'GET',
            '/api/public/agency/booking-groups/'.$created['publicToken'].'/ticket/pdf',
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF', $this->client->getResponse()->getContent() ?: '');
    }

    public function testCancelUnpaidGroupBooking(): void
    {
        $ws = $this->createOnlineOfferWorkspace();
        $created = $this->createGroupBooking($ws, ['02D', '01C']);

        $this->publicPost('/api/public/agency/booking-groups/'.$created['publicToken'].'/cancel', null, 200);
        $body = $this->decodeJsonResponse();

        self::assertSame(AgencyBookingGroup::STATUS_CANCELLED, $body['status'] ?? null);
    }

    /**
     * @param list<string> $seats
     *
     * @return array<string, mixed>
     */
    private function createGroupBooking(array $ws, array $seats): array
    {
        $passengers = array_map(static fn (string $seat): array => ['seatNumber' => $seat], $seats);

        return $this->publicPost('/api/public/agency/booking-groups', [
            'offerId' => $ws['offer']->getId(),
            'travelDate' => $this->travelDate('+7 days'),
            'groupName' => 'Famille Kabongo',
            'contactPhone' => '+243812345678',
            'passengers' => $passengers,
        ], 201);
    }

    /**
     * @return array{offer: AgencyOffer, agency: \App\Entity\Agency, token: string}
     */
    private function createOnlineOfferWorkspace(): array
    {
        $ws = $this->createPartnerWorkspace('GroupBooking');
        $offer = $this->em->find(AgencyOffer::class, $ws['offer']->getId());
        self::assertInstanceOf(AgencyOffer::class, $offer);
        $offer->setOnlineSales(true);
        $offer->setBookingHoldMinutes(15);
        $this->em->flush();

        return $ws;
    }

    /**
     * @param array<string, mixed>|null $body
     *
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
