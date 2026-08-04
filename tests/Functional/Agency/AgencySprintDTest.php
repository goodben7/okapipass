<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyPayment;

/**
 * Sprint D smoke: me/summary, caisse, staff, refund.
 */
final class AgencySprintDTest extends AgencyApiTestCase
{
    public function testAgencyMeExposesStaffRoleAndCurrencies(): void
    {
        $ws = $this->createPartnerWorkspace('ME');
        $me = $this->api('GET', '/api/agency/me', $ws['token'], null, 200);

        self::assertSame('ADMIN', $me['staffRole'] ?? null);
        self::assertContains('CDF', $me['agency']['supportedCurrencies'] ?? []);
        self::assertContains('payment:write', $me['permissions'] ?? []);
    }

    public function testDeclarationSummaryIncludesByCurrency(): void
    {
        $ws = $this->createPartnerWorkspace('SUM');
        $summary = $this->api('GET', '/api/agency/declarations/summary', $ws['token'], null, 200);

        self::assertArrayHasKey('fptDue', $summary);
        self::assertArrayHasKey('byCurrency', $summary);
        self::assertIsArray($summary['byCurrency']);
    }

    public function testCounterPaymentAndRefundFlow(): void
    {
        $ws = $this->createPartnerWorkspace('PAY');
        $ticket = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Cash Client',
            'passengerId' => 'CD-PAY-1',
            'passengerPhone' => '+243890000001',
            'seatNumber' => '02B',
            'travelDate' => $this->travelDate(),
            'sendSms' => false,
        ], 201);
        $ticketId = $this->extractId($ticket) ?? ($ticket['ticket']['id'] ?? null);
        self::assertNotNull($ticketId);

        $payment = $this->api('POST', '/api/agency/payments', $ws['token'], [
            'ticket' => $ticketId,
            'method' => AgencyPayment::METHOD_CASH,
        ], 201);

        self::assertSame(AgencyPayment::STATUS_PAID, $payment['status'] ?? null);
        self::assertSame(88000, $payment['amount'] ?? -1); // 85000 + 3000

        $refunded = $this->api('POST', '/api/agency/payments/'.$payment['id'].'/refund', $ws['token'], null, 200);
        self::assertSame(AgencyPayment::STATUS_REFUNDED, $refunded['status'] ?? null);

        $ticketAfter = $this->api('GET', '/api/agency/tickets/'.$ticketId, $ws['token'], null, 200);
        self::assertSame('CANCELLED', $ticketAfter['status'] ?? null);
    }

    public function testStaffCreateAndRoleUpdate(): void
    {
        $ws = $this->createPartnerWorkspace('STAFF');
        $staffEmail = sprintf('cashier_%s@agency.test', $this->suffix);

        $member = $this->api('POST', '/api/agency/staff', $ws['token'], [
            'email' => $staffEmail,
            'password' => 'CashierPass1!',
            'displayName' => 'Caissier Test',
            'role' => 'CASHIER',
        ], 201);

        self::assertSame('CASHIER', $member['role'] ?? null);
        self::assertNotNull($member['id'] ?? null);

        $updated = $this->api('PATCH', '/api/agency/staff/'.$member['id'], $ws['token'], [
            'role' => 'READONLY',
        ], 200);
        self::assertSame('READONLY', $updated['role'] ?? null);

        $staffToken = $this->authenticate($staffEmail, 'CashierPass1!');
        $me = $this->api('GET', '/api/agency/me', $staffToken, null, 200);
        self::assertSame('READONLY', $me['staffRole'] ?? null);
        self::assertNotContains('payment:write', $me['permissions'] ?? []);
    }

    public function testTicketRefundWithoutPriorPayment(): void
    {
        $ws = $this->createPartnerWorkspace('REF');
        $ticket = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Refund Only',
            'passengerId' => 'CD-REF-1',
            'passengerPhone' => '+243890000099',
            'seatNumber' => '02C',
            'travelDate' => $this->travelDate(),
            'sendSms' => false,
        ], 201);
        $ticketId = $this->extractId($ticket) ?? ($ticket['ticket']['id'] ?? null);

        $refunded = $this->api('POST', '/api/agency/tickets/'.$ticketId.'/refund', $ws['token'], null, 200);
        self::assertSame('CANCELLED', $refunded['status'] ?? null);
    }
}
