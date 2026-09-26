<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyPayment;
use App\Entity\CashHandover;
use App\Entity\PosSession;

final class PosAndLoyaltyTest extends AgencyApiTestCase
{
    public function testPosSessionSaleAndCashHandover(): void
    {
        $ws = $this->createPartnerWorkspace('PosCash');
        $travelDate = $this->travelDate('+4 days');

        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-1',
        ], 201);
        self::assertSame(PosSession::STATUS_OPEN, $session['status'] ?? null);
        $sessionId = $session['id'] ?? null;
        self::assertNotNull($sessionId);

        $sale = $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $sessionId,
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Client POS',
            'passengerId' => 'CD-POS-1',
            'passengerPhone' => '+243830011122',
            'seatNumber' => '01C',
            'travelDate' => $travelDate,
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 201);
        self::assertNotEmpty($sale['ticketId'] ?? null);
        self::assertNotEmpty($sale['paymentId'] ?? null);

        $closed = $this->api('POST', '/api/agency/pos/sessions/'.$sessionId.'/close', $ws['token'], null, 200);
        self::assertSame(PosSession::STATUS_CLOSED, $closed['status'] ?? null);

        $handover = $this->api('POST', '/api/agency/pos/cash-handovers', $ws['token'], [
            'session' => $sessionId,
            'declaredAmount' => $sale['amount'] ?? 0,
        ], 201);
        self::assertSame(CashHandover::STATUS_PENDING, $handover['status'] ?? null);
        $handoverId = $handover['id'] ?? null;
        self::assertNotNull($handoverId);

        $confirmed = $this->api('POST', '/api/agency/pos/cash-handovers/'.$handoverId.'/confirm', $ws['token'], null, 200);
        self::assertSame(CashHandover::STATUS_CONFIRMED, $confirmed['status'] ?? null);
    }

    public function testPromotionValidateAndQuoteDiscount(): void
    {
        $ws = $this->createPartnerWorkspace('Promo');
        $promo = $this->api('POST', '/api/agency/promotions', $ws['token'], [
            'code' => 'MBIYO10',
            'label' => 'Promo 10%',
            'discountType' => 'percent_off',
            'discountValue' => 10,
            'active' => true,
        ], 201);
        self::assertSame('MBIYO10', $promo['code'] ?? null);

        $validated = $this->api('POST', '/api/agency/promotions/validate', $ws['token'], [
            'code' => 'MBIYO10',
            'ticketPrice' => 10000,
        ], 200);
        self::assertSame(1000, $validated['discountAmount'] ?? null);
        self::assertSame(9000, $validated['finalTicketPrice'] ?? null);
    }
}
