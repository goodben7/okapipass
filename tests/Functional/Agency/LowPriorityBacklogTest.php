<?php

namespace App\Tests\Functional\Agency;

use App\Domain\Agency\AgencyStaffRole;
use App\Entity\AgencyPayment;

final class LowPriorityBacklogTest extends AgencyApiTestCase
{
    public function testBlacklistBlocksPosSale(): void
    {
        $ws = $this->createPartnerWorkspace('BlList');
        $phone = '+2438300'.random_int(100000, 999999);

        $entry = $this->api('POST', '/api/agency/blacklist', $ws['token'], [
            'type' => 'PHONE',
            'value' => $phone,
            'reason' => 'fraud',
            'active' => true,
        ], 201);
        self::assertStringStartsWith('BL', (string) ($entry['id'] ?? ''));

        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-BL',
        ], 201);

        $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Blocked User',
            'passengerId' => 'CD-BL-1',
            'passengerPhone' => $phone,
            'seatNumber' => '01A',
            'travelDate' => $this->travelDate('+5 days'),
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 422);
    }

    public function testAuditLogListAfterPosSale(): void
    {
        $ws = $this->createPartnerWorkspace('Audit');
        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-AL',
        ], 201);

        $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Audit Client',
            'passengerId' => 'CD-AL-1',
            'passengerPhone' => '+2438311'.random_int(100000, 999999),
            'seatNumber' => '01B',
            'travelDate' => $this->travelDate('+5 days'),
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 201);

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $logs = $this->api(
            'GET',
            '/api/agency/audit-logs?from='.$today.'&to='.$today.'&action=pos.sale',
            $ws['token'],
            null,
            200,
        );
        $members = $this->collectionMembers($logs);
        self::assertNotEmpty($members);
        self::assertStringStartsWith('AL', (string) ($members[0]['id'] ?? ''));
    }

    public function testIdempotencyKeyReplaysPosSale(): void
    {
        $ws = $this->createPartnerWorkspace('Idem');
        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-IK',
        ], 201);

        $key = 'idem-'.$this->suffix;
        $body = [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Idem Client',
            'passengerId' => 'CD-IK-1',
            'passengerPhone' => '+2438322'.random_int(100000, 999999),
            'seatNumber' => '01C',
            'travelDate' => $this->travelDate('+6 days'),
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ];

        $first = $this->apiWithHeaders('POST', '/api/agency/pos/sales', $ws['token'], $body, [
            'HTTP_IDEMPOTENCY_KEY' => $key,
        ], 201);
        $second = $this->apiWithHeaders('POST', '/api/agency/pos/sales', $ws['token'], $body, [
            'HTTP_IDEMPOTENCY_KEY' => $key,
        ], 201);

        self::assertSame($first['ticketId'] ?? null, $second['ticketId'] ?? null);
        self::assertSame($first['paymentId'] ?? null, $second['paymentId'] ?? null);
        self::assertSame($first['amount'] ?? null, $second['amount'] ?? null);
    }

    public function testCorridorAnalyticsAndReschedule(): void
    {
        $ws = $this->createPartnerWorkspace('Corr');
        $travelDate = $this->travelDate('+7 days');

        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-C',
        ], 201);
        $sale = $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Corr Client',
            'passengerId' => 'CD-C-1',
            'passengerPhone' => '+2438333'.random_int(100000, 999999),
            'seatNumber' => '02A',
            'travelDate' => $travelDate,
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 201);

        $from = (new \DateTimeImmutable())->format('Y-m-d');
        $to = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');
        $corr = $this->api(
            'GET',
            '/api/agency/analytics/corridors?from='.$from.'&to='.$to,
            $ws['token'],
            null,
            200,
        );
        self::assertArrayHasKey('corridors', $corr);
        self::assertIsArray($corr['corridors']);

        $newDate = $this->travelDate('+8 days');
        $rescheduled = $this->api('POST', '/api/agency/tickets/'.$sale['ticketId'].'/reschedule', $ws['token'], [
            'travelDate' => $newDate,
            'seatNumber' => '02B',
        ], 200);
        $travelDateOut = (string) ($rescheduled['travelDate'] ?? '');
        self::assertStringStartsWith($newDate, $travelDateOut);
        self::assertSame('02B', strtoupper((string) ($rescheduled['seatNumber'] ?? '')));
    }

    public function testNewStaffRolesAccepted(): void
    {
        $ws = $this->createPartnerWorkspace('Roles');

        $driver = $this->api('POST', '/api/agency/staff', $ws['token'], [
            'email' => sprintf('driver_%s@agency.test', $this->suffix),
            'password' => 'DriverPass1!',
            'displayName' => 'Chauffeur',
            'role' => AgencyStaffRole::DRIVER,
        ], 201);
        self::assertSame(AgencyStaffRole::DRIVER, $driver['role'] ?? null);

        $accountant = $this->api('POST', '/api/agency/staff', $ws['token'], [
            'email' => sprintf('acc_%s@agency.test', $this->suffix),
            'password' => 'AccPass123!',
            'displayName' => 'Comptable',
            'role' => AgencyStaffRole::ACCOUNTANT,
        ], 201);
        self::assertSame(AgencyStaffRole::ACCOUNTANT, $accountant['role'] ?? null);

        $perms = AgencyStaffRole::permissionsFor(AgencyStaffRole::FLEET_MANAGER);
        self::assertContains('fleet:write', $perms);
        self::assertContains('driver:write', $perms);
        self::assertContains('maintenance:write', $perms);

        $driverPerms = AgencyStaffRole::permissionsFor(AgencyStaffRole::DRIVER);
        self::assertContains('fleet:read', $driverPerms);
        self::assertContains('embarkation:write', $driverPerms);
    }

    public function testWebhookCrudAndPriceHistory(): void
    {
        $ws = $this->createPartnerWorkspace('WhPh');

        $wh = $this->api('POST', '/api/agency/webhooks', $ws['token'], [
            'url' => 'https://example.com/hooks/okapi',
            'secret' => 'sec-'.$this->suffix,
            'events' => ['payment.paid', 'ticket.issued'],
            'active' => true,
        ], 201);
        self::assertStringStartsWith('WH', (string) ($wh['id'] ?? ''));

        $list = $this->api('GET', '/api/agency/webhooks', $ws['token'], null, 200);
        self::assertNotEmpty($this->collectionMembers($list));

        $patched = $this->api('PATCH', '/api/agency/offers/'.$ws['offer']->getId(), $ws['token'], [
            'ticketPrice' => 90000,
        ], 200);
        self::assertSame(90000, $patched['ticketPrice'] ?? null);
    }
}
