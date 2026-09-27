<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyOffer;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Entity\PosSession;

/**
 * Vague 9 Phase B — URBAN capacity-only sales + validate-boarding.
 */
final class UrbanCapacityTest extends AgencyApiTestCase
{
    public function testCreateUrbanOfferSeatModeAndPublicCatalog(): void
    {
        $ws = $this->createPartnerWorkspace('UrbanCat', 6);

        $offer = $this->api('POST', '/api/agency/offers', $ws['token'], [
            'label' => 'Ligne urbaine '.$this->suffix,
            'origin' => 'Gare Centrale',
            'destination' => 'Limete',
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'ticketPrice' => 1500,
            'currency' => 'CDF',
            'departureTime' => '07:00',
            'durationMinutes' => 45,
            'serviceType' => AgencyOffer::SERVICE_URBAN,
            'onlineSales' => true,
            'active' => true,
        ], 201);

        self::assertSame(AgencyOffer::SERVICE_URBAN, $offer['serviceType'] ?? null);
        self::assertSame(AgencyOffer::SEAT_CAPACITY, $offer['seatMode'] ?? null);
        self::assertTrue($offer['onlineSales'] ?? false);

        $offerId = (string) ($offer['id'] ?? '');
        self::assertNotSame('', $offerId);

        /** @var \App\Repository\AgencyOfferRepository $repo */
        $repo = static::getContainer()->get(\App\Repository\AgencyOfferRepository::class);
        self::assertNotNull($repo->findPublicOnlineById($offerId));

        $this->client->request(
            'GET',
            '/api/public/agency/offers?agencyId='.$ws['agency']->getId(),
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true);
        self::assertIsArray($payload);
        $ids = array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $this->collectionMembers($payload),
        );
        self::assertContains($offerId, $ids);

        $members = $this->collectionMembers($payload);
        $urban = null;
        foreach ($members as $row) {
            if (($row['id'] ?? null) === $offerId) {
                $urban = $row;
                break;
            }
        }
        self::assertNotNull($urban);
        self::assertSame(AgencyOffer::SERVICE_URBAN, $urban['serviceType'] ?? null);
        self::assertSame(AgencyOffer::SEAT_CAPACITY, $urban['seatMode'] ?? null);
    }

    public function testAgencyBookingWithoutSeatDefaultsToGa(): void
    {
        $ws = $this->createPartnerWorkspace('UrbanBk', 8);
        $offer = $this->createUrbanOffer($ws, capacity: 8);
        $date = $this->travelDate('+3 days');

        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$offer['id'],
            'passengerName' => 'Urban Pax',
            'passengerId' => 'CD-URB-1',
            'passengerPhone' => '+243890011001',
            'travelDate' => $date,
            'sendSms' => false,
        ], 201);

        $seat = (string) ($booking['booking']['seatNumber'] ?? '');
        self::assertNotSame('', $seat);
        self::assertTrue(
            'GA' === $seat || str_starts_with($seat, 'GA'),
            'Expected GA seat, got: '.$seat
        );
    }

    public function testSellUntilCapacityThen409(): void
    {
        $ws = $this->createPartnerWorkspace('UrbanFull', 2);
        $offer = $this->createUrbanOffer($ws, capacity: 2, transportId: $ws['transport']->getId());
        $date = $this->travelDate('+4 days');

        for ($i = 1; $i <= 2; ++$i) {
            $this->api('POST', '/api/agency/bookings', $ws['token'], [
                'offer' => '/api/agency/offers/'.$offer['id'],
                'passengerName' => 'Pax '.$i,
                'passengerId' => 'CD-CAP-'.$i,
                'passengerPhone' => '+24389002'.sprintf('%04d', $i),
                'travelDate' => $date,
                'sendSms' => false,
            ], 201);
        }

        $conflict = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$offer['id'],
            'passengerName' => 'Pax Overflow',
            'passengerId' => 'CD-CAP-3',
            'passengerPhone' => '+243890029999',
            'travelDate' => $date,
            'sendSms' => false,
        ], 409);

        self::assertStringContainsString('CAPACITY_FULL', (string) json_encode($conflict));
    }

    public function testPosSaleUrbanWithoutSeatNumber(): void
    {
        $ws = $this->createPartnerWorkspace('UrbanPos', 8);
        $offer = $this->createUrbanOffer($ws, capacity: 8);
        $date = $this->travelDate('+5 days');

        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-Urban',
        ], 201);
        self::assertSame(PosSession::STATUS_OPEN, $session['status'] ?? null);

        $sale = $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$offer['id'],
            'passengerName' => 'POS Urban',
            'passengerId' => 'CD-POS-U1',
            'passengerPhone' => '+243830011199',
            'travelDate' => $date,
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 201);

        self::assertNotEmpty($sale['ticketId'] ?? null);

        $this->em->clear();
        /** @var AgencyTicket $ticket */
        $ticket = $this->em->getRepository(AgencyTicket::class)->find($sale['ticketId']);
        self::assertInstanceOf(AgencyTicket::class, $ticket);
        self::assertTrue(
            'GA' === $ticket->getSeatNumber() || str_starts_with((string) $ticket->getSeatNumber(), 'GA'),
            'Expected GA seat on POS ticket'
        );
    }

    public function testValidateBoardingOnTrip(): void
    {
        $ws = $this->createPartnerWorkspace('UrbanBoard', 8);
        $offer = $this->createUrbanOffer($ws, capacity: 8);
        $date = $this->travelDate('+6 days');

        $ticketResult = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$offer['id'],
            'passengerName' => 'Boarder',
            'passengerId' => 'CD-BRD-1',
            'passengerPhone' => '+243890033001',
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
        $qrToken = $ticket->getQrToken();
        self::assertNotEmpty($qrToken);

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Course urbaine',
            'offer' => '/api/agency/offers/'.$offer['id'],
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'departureDate' => $date,
            'departureTime' => '07:00',
        ], 201);

        $result = $this->api('POST', '/api/agency/trips/'.$embarkation['id'].'/validate-boarding', $ws['token'], [
            'token' => $qrToken,
        ], 200);

        self::assertSame($ticketId, $result['ticketId'] ?? null);
        self::assertSame(AgencyTicket::STATUS_BOARDED, $result['status'] ?? null);
        self::assertGreaterThanOrEqual(1, (int) ($result['boardedCount'] ?? 0));
    }

    public function testSchoolStillExcludedFromPublicCatalog(): void
    {
        $ws = $this->createPartnerWorkspace('SchoolHide', 8);

        $offer = $this->api('POST', '/api/agency/offers', $ws['token'], [
            'label' => 'Navette scolaire URBAN check',
            'origin' => 'Kimwenza',
            'destination' => 'Lycée',
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'ticketPrice' => 0,
            'currency' => 'CDF',
            'departureTime' => '06:30',
            'durationMinutes' => 40,
            'serviceType' => AgencyOffer::SERVICE_SCHOOL,
            'onlineSales' => true,
            'active' => true,
        ], 201);

        self::assertSame(AgencyOffer::SERVICE_SCHOOL, $offer['serviceType'] ?? null);
        self::assertSame(AgencyOffer::SEAT_NONE, $offer['seatMode'] ?? null);
        self::assertFalse($offer['onlineSales'] ?? true);

        $offerId = (string) ($offer['id'] ?? '');
        /** @var \App\Repository\AgencyOfferRepository $repo */
        $repo = static::getContainer()->get(\App\Repository\AgencyOfferRepository::class);
        self::assertNull($repo->findPublicOnlineById($offerId));
    }

    /**
     * @param array{token: string, transport: \App\Entity\AgencyTransport, agency: \App\Entity\Agency} $ws
     *
     * @return array<string, mixed>
     */
    private function createUrbanOffer(array $ws, int $capacity = 8, ?string $transportId = null): array
    {
        // Workspace transport capacity is set at createPartnerWorkspace; reuse it.
        unset($capacity);

        return $this->api('POST', '/api/agency/offers', $ws['token'], [
            'label' => 'Urban '.$this->suffix,
            'origin' => 'Centre',
            'destination' => 'Périphérie',
            'transport' => '/api/agency/transports/'.($transportId ?? $ws['transport']->getId()),
            'ticketPrice' => 2000,
            'currency' => 'CDF',
            'departureTime' => '08:00',
            'durationMinutes' => 30,
            'serviceType' => AgencyOffer::SERVICE_URBAN,
            'onlineSales' => true,
            'active' => true,
        ], 201);
    }
}
