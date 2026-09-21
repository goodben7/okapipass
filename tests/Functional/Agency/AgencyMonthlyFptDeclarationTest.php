<?php

namespace App\Tests\Functional\Agency;

use App\Entity\PassDeclaration;

final class AgencyMonthlyFptDeclarationTest extends AgencyApiTestCase
{
    public function testGenerateMonthlyDeclarationFromTickets(): void
    {
        $ws = $this->createPartnerWorkspace('MonthlyFpt');
        $yearMonth = (new \DateTimeImmutable('+10 days'))->format('Y-m');
        $travelDate = $this->travelDate('+10 days');

        $this->issueTicket($ws, '01A', $travelDate, 'Jean Kabongo', 'CD-M1');
        $this->issueTicket($ws, '01B', $travelDate, 'Marie Kabongo', 'CD-M2');

        $declaration = $this->api('POST', '/api/agency/declarations/generate-monthly', $ws['token'], [
            'yearMonth' => $yearMonth,
        ], 201);

        self::assertSame(PassDeclaration::SOURCE_MONTHLY, $declaration['source'] ?? null);
        self::assertSame(PassDeclaration::STATUS_DRAFT, $declaration['status'] ?? null);
        self::assertSame($yearMonth, $declaration['periodMonth'] ?? null);
        self::assertSame(6000, $declaration['fptTotal'] ?? -1);
        self::assertCount(2, $declaration['lines'] ?? []);
    }

    public function testGenerateMonthlyIsIdempotent(): void
    {
        $ws = $this->createPartnerWorkspace('MonthlyIdem');
        $yearMonth = (new \DateTimeImmutable('+11 days'))->format('Y-m');
        $travelDate = $this->travelDate('+11 days');

        $this->issueTicket($ws, '01C', $travelDate, 'Paul Mbuyi', 'CD-M3');

        $first = $this->api('POST', '/api/agency/declarations/generate-monthly', $ws['token'], [
            'yearMonth' => $yearMonth,
        ], 201);

        $second = $this->api('POST', '/api/agency/declarations/generate-monthly', $ws['token'], [
            'yearMonth' => $yearMonth,
        ], 201);

        self::assertSame($first['id'] ?? null, $second['id'] ?? null);
        self::assertSame(3000, $second['fptTotal'] ?? -1);
    }

    public function testGenerateMonthlyExcludesAlreadyDeclaredTickets(): void
    {
        $ws = $this->createPartnerWorkspace('MonthlyExcl');
        $yearMonth = (new \DateTimeImmutable('+12 days'))->format('Y-m');
        $travelDate = $this->travelDate('+12 days');

        $ticket = $this->issueTicket($ws, '01D', $travelDate, 'Sarah Ilunga', 'CD-M4');
        $ticketId = $this->extractId($ticket) ?? ($ticket['ticket']['id'] ?? null);
        self::assertNotNull($ticketId);

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Départ monthly',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'departureDate' => $travelDate,
            'departureTime' => '06:00',
            'ticketIds' => [$ticketId],
        ], 201);

        $this->api('POST', '/api/agency/embarkations/'.$embarkation['id'].'/declare', $ws['token'], null, 201);

        $this->api('POST', '/api/agency/declarations/generate-monthly', $ws['token'], [
            'yearMonth' => $yearMonth,
        ], 422);
    }

    public function testGenerateMonthlyEmptyPeriodReturns422(): void
    {
        $ws = $this->createPartnerWorkspace('MonthlyEmpty');

        $this->api('POST', '/api/agency/declarations/generate-monthly', $ws['token'], [
            'yearMonth' => '2020-01',
        ], 422);
    }

    public function testGenerateMonthlyInvalidYearMonthReturns400Or422(): void
    {
        $ws = $this->createPartnerWorkspace('MonthlyBad');

        $status = $this->apiStatus('POST', '/api/agency/declarations/generate-monthly', $ws['token'], [
            'yearMonth' => '2026-13',
        ]);

        self::assertContains($status, [400, 422]);
    }

    /**
     * @param array{token: string, offer: \App\Entity\AgencyOffer} $ws
     *
     * @return array<string, mixed>
     */
    private function issueTicket(array $ws, string $seat, string $travelDate, string $name, string $passengerId): array
    {
        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => $name,
            'passengerId' => $passengerId,
            'passengerPhone' => '+2438100'.substr($passengerId, -5),
            'seatNumber' => $seat,
            'travelDate' => $travelDate,
            'status' => 'CONFIRMED',
            'sendSms' => false,
        ], 201);

        $bookingId = $this->extractId($booking);
        self::assertNotNull($bookingId);

        return $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function apiStatus(string $method, string $uri, string $token, ?array $body = null): int
    {
        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => 'application/json',
        ];
        if (null !== $body) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse()->getStatusCode();
    }
}
