<?php

namespace App\Tests\Functional\Traveler;

use App\Entity\AgencyTicket;
use App\Model\UserProxyIntertace;
use App\Tests\Functional\Agency\AgencyApiTestCase;

final class TravelerOtpAndTicketsTest extends AgencyApiTestCase
{
    public function testOtpLoginAndListTicketsByPhone(): void
    {
        $ws = $this->createPartnerWorkspace('TravOtp');
        $phone = '+2438100'.random_int(100000, 999999);
        $travelDate = $this->travelDate('+3 days');

        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Marie Voyageur',
            'passengerId' => 'CD-TV-1',
            'passengerPhone' => $phone,
            'seatNumber' => '01A',
            'travelDate' => $travelDate,
            'status' => 'CONFIRMED',
            'sendSms' => false,
        ], 201);
        $bookingId = $this->extractId($booking);
        self::assertNotNull($bookingId);
        $ticket = $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);
        $ticketId = $ticket['id'] ?? $ticket['ticket']['id'] ?? null;
        if (null === $ticketId && isset($ticket['@id'])) {
            $ticketId = basename((string) $ticket['@id']);
        }
        // issue-ticket may return AgencyTicket nested
        if (null === $ticketId) {
            $issued = $this->em->getRepository(AgencyTicket::class)->findOneBy(['passengerPhone' => $phone]);
            self::assertInstanceOf(AgencyTicket::class, $issued);
            $ticketId = $issued->getId();
        }

        $otp = $this->publicApi('POST', '/api/public/auth/otp/request', [
            'phone' => $phone,
        ], 200);
        self::assertSame($phone, $otp['phone'] ?? null);
        self::assertNotEmpty($otp['debugCode'] ?? null);

        $verified = $this->publicApi('POST', '/api/public/auth/otp/verify', [
            'phone' => $phone,
            'code' => $otp['debugCode'],
        ], 200);
        self::assertNotEmpty($verified['token'] ?? null);
        $token = (string) $verified['token'];

        $me = $this->api('GET', '/api/traveler/me', $token, null, 200);
        self::assertSame($phone, $me['phone'] ?? null);
        self::assertSame(UserProxyIntertace::PERSON_TRAVELER, $me['personType'] ?? null);

        $list = $this->api('GET', '/api/traveler/tickets', $token, null, 200);
        $member = $list['member'] ?? $list['hydra:member'] ?? (array_is_list($list) ? $list : []);
        self::assertNotEmpty($member);
        $ids = array_column($member, 'id');
        self::assertContains($ticketId, $ids);

        $item = $this->api('GET', '/api/traveler/tickets/'.$ticketId, $token, null, 200);
        self::assertSame($ticketId, $item['id'] ?? null);

        $share = $this->api('POST', '/api/traveler/tickets/'.$ticketId.'/share', $token, [
            'toPhone' => '+243820011122',
        ], 200);
        self::assertNotEmpty($share['smsMessageId'] ?? null);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function publicApi(string $method, string $uri, ?array $body, int $expectedStatus): array
    {
        $this->client->request(
            $method,
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );
        $content = $this->client->getResponse()->getContent() ?: '{}';
        $status = $this->client->getResponse()->getStatusCode();
        self::assertSame($expectedStatus, $status, sprintf('%s %s: %s', $method, $uri, $content));
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
