<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyPayment;
use App\Entity\AgencyRentalContract;

final class AgencyFleetPolishTest extends AgencyApiTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function createConfirmedRental(array $ws): array
    {
        $date = $this->travelDate('+11 days');
        $created = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'clientName' => 'Polish Client',
            'clientPhone' => '+243810000201',
            'startAt' => $date.'T08:00:00',
            'endAt' => (new \DateTimeImmutable($date))->modify('+2 days')->format('Y-m-d').'T18:00:00',
            'dailyRate' => 120000,
            'totalAmount' => 360000,
            'depositAmount' => 100000,
        ], 201);

        $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/confirm', $ws['token']);

        return $created;
    }

    public function testRentalCashPayment(): void
    {
        $ws = $this->createPartnerWorkspace('PolishCash');
        $contract = $this->createConfirmedRental($ws);

        $payment = $this->api('POST', '/api/agency/rental-contracts/'.$contract['id'].'/payments', $ws['token'], [
            'method' => AgencyPayment::METHOD_CASH,
        ], 201);

        self::assertSame(AgencyPayment::STATUS_PAID, $payment['status'] ?? null);
        self::assertSame(AgencyPayment::CHANNEL_RENTAL, $payment['channel'] ?? null);
        self::assertSame(100000, $payment['amount'] ?? null);
    }

    public function testRentalMobileMoneyPaymentCanBeChecked(): void
    {
        $ws = $this->createPartnerWorkspace('PolishMM');
        $contract = $this->createConfirmedRental($ws);

        $payment = $this->api('POST', '/api/agency/rental-contracts/'.$contract['id'].'/payments', $ws['token'], [
            'method' => AgencyPayment::METHOD_MOBILE_MONEY,
            'amount' => 360000,
        ], 201);

        self::assertSame(AgencyPayment::STATUS_PENDING, $payment['status'] ?? null);

        $checked = $this->api('POST', '/api/agency/rental-contracts/'.$contract['id'].'/payments/check-status', $ws['token']);
        self::assertSame(AgencyPayment::STATUS_PAID, $checked['status'] ?? null);
    }

    public function testRentalContractPdfDownload(): void
    {
        $ws = $this->createPartnerWorkspace('PolishPdf');
        $contract = $this->createConfirmedRental($ws);

        $this->client->request(
            'GET',
            '/api/agency/rental-contracts/'.$contract['id'].'/pdf',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$ws['token'],
                'HTTP_ACCEPT' => 'application/pdf',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF', $this->client->getResponse()->getContent() ?: '');
    }

    public function testMaintenanceCaseCreationStillWorksAfterNotifierHook(): void
    {
        $ws = $this->createPartnerWorkspace('PolishMaint');

        $created = $this->api('POST', '/api/agency/maintenance-cases', $ws['token'], [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'type' => 'REPAIR',
            'title' => 'Polish maintenance alert',
        ], 201);

        self::assertSame('OPEN', $created['status'] ?? null);
    }

    public function testLicenseAlertCommandRuns(): void
    {
        $ws = $this->createPartnerWorkspace('PolishLic');
        $this->api('POST', '/api/agency/drivers', $ws['token'], [
            'fullName' => 'Expiring Driver',
            'phone' => '+243810000202',
            'licenseNumber' => 'EXP-'.strtoupper($this->suffix),
            'licenseExpiresAt' => (new \DateTimeImmutable('+10 days'))->format('Y-m-d'),
        ], 201);

        $exitCode = null;
        $output = [];
        exec('cd '.escapeshellarg(dirname(__DIR__, 3)).' && APP_ENV=test php bin/console app:fleet:send-license-alerts --days=30 2>&1', $output, $exitCode);

        self::assertSame(0, $exitCode);
        self::assertNotEmpty($output);
    }

    public function testDuplicateRentalPaymentRejected(): void
    {
        $ws = $this->createPartnerWorkspace('PolishDupPay');
        $contract = $this->createConfirmedRental($ws);

        $this->api('POST', '/api/agency/rental-contracts/'.$contract['id'].'/payments', $ws['token'], [
            'method' => AgencyPayment::METHOD_CASH,
        ], 201);

        $this->api('POST', '/api/agency/rental-contracts/'.$contract['id'].'/payments', $ws['token'], [
            'method' => AgencyPayment::METHOD_CASH,
        ], 409);
    }
}
