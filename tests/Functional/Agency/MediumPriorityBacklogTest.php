<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyDriver;
use App\Entity\AgencyPayment;
use App\Entity\Promotion;

final class MediumPriorityBacklogTest extends AgencyApiTestCase
{
    public function testPromoMaxDiscountCap(): void
    {
        $ws = $this->createPartnerWorkspace('PromoCap');

        $promo = $this->api('POST', '/api/agency/promotions', $ws['token'], [
            'code' => 'CAP'.strtoupper($this->suffix),
            'label' => 'Cap promo',
            'discountType' => Promotion::DISCOUNT_PERCENT_OFF,
            'discountValue' => 50,
            'maxDiscountAmount' => 2000,
            'active' => true,
        ], 201);

        self::assertStringStartsWith('PM', (string) ($promo['id'] ?? ''));
        self::assertSame(2000, $promo['maxDiscountAmount'] ?? null);

        $validated = $this->api('POST', '/api/agency/promotions/validate', $ws['token'], [
            'code' => 'CAP'.strtoupper($this->suffix),
            'ticketPrice' => 20000,
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
        ], 200);

        self::assertSame(2000, $validated['discountAmount'] ?? null);
    }

    public function testPosKpiEndpointSmoke(): void
    {
        $ws = $this->createPartnerWorkspace('PosKpi');
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $payment = new AgencyPayment();
        $payment->setAgency($ws['agency']);
        $payment->setReference('ABP-KPI-'.strtoupper($this->suffix));
        $payment->setAmount(12000);
        $payment->setCurrency('CDF');
        $payment->setMethod(AgencyPayment::METHOD_CASH);
        $payment->setStatus(AgencyPayment::STATUS_PAID);
        $payment->setChannel(AgencyPayment::CHANNEL_POS);
        $payment->setProvider(AgencyPayment::PROVIDER_FLEXPAY);
        $payment->setPaidAt(new \DateTimeImmutable());
        $this->em->persist($payment);
        $this->em->flush();

        $result = $this->api(
            'GET',
            '/api/agency/pos/kpi?date='.$today,
            $ws['token'],
            null,
            200,
        );

        self::assertSame($today, $result['date'] ?? null);
        self::assertArrayHasKey('ticketsCount', $result);
        self::assertArrayHasKey('salesCount', $result);
        self::assertArrayHasKey('caTotal', $result);
        self::assertArrayHasKey('mix', $result);
        self::assertArrayHasKey('bySeller', $result);
        self::assertArrayHasKey('byPointOfSale', $result);
        self::assertIsArray($result['mix']);
    }

    public function testFamilyBeneficiaryCrudAsTraveler(): void
    {
        $phone = '+2438100'.random_int(100000, 999999);

        $otp = $this->publicApi('POST', '/api/public/auth/otp/request', ['phone' => $phone], 200);
        $verified = $this->publicApi('POST', '/api/public/auth/otp/verify', [
            'phone' => $phone,
            'code' => $otp['debugCode'],
        ], 200);
        $token = $verified['token'] ?? null;
        self::assertNotEmpty($token);

        $created = $this->api('POST', '/api/traveler/family', $token, [
            'fullName' => 'Enfant Okapi',
            'phone' => '+2438200'.random_int(100000, 999999),
            'relation' => 'CHILD',
            'dateOfBirth' => '2015-06-01',
        ], 201);

        self::assertStringStartsWith('TB', (string) ($created['id'] ?? ''));
        self::assertSame('CHILD', $created['relation'] ?? null);
        $id = $created['id'];

        $list = $this->api('GET', '/api/traveler/family', $token, null, 200);
        $members = $list['member'] ?? $list['hydra:member'] ?? (isset($list[0]) ? $list : null);
        if (null === $members && isset($list['id'])) {
            $members = [$list];
        }
        self::assertIsArray($members);
        self::assertGreaterThanOrEqual(1, \count($members));

        $patched = $this->api('PATCH', '/api/traveler/family/'.$id, $token, [
            'fullName' => 'Enfant Okapi Jr',
        ], 200);
        self::assertSame('Enfant Okapi Jr', $patched['fullName'] ?? null);

        $this->api('DELETE', '/api/traveler/family/'.$id, $token, null, 204);
    }

    public function testDriverDocumentCreateAndList(): void
    {
        $ws = $this->createPartnerWorkspace('DrvDoc');

        $driver = new AgencyDriver();
        $driver->setAgency($ws['agency']);
        $driver->setFullName('Chauffeur Test');
        $driver->setPhone('+2438900'.random_int(100000, 999999));
        $driver->setLicenseNumber('LIC-'.strtoupper($this->suffix));
        $driver->setLicenseExpiresAt(new \DateTimeImmutable('+1 year'));
        $driver->setStatus(AgencyDriver::STATUS_ACTIVE);
        $this->em->persist($driver);
        $this->em->flush();

        $doc = $this->api('POST', '/api/agency/driver-documents', $ws['token'], [
            'driver' => '/api/agency/drivers/'.$driver->getId(),
            'type' => 'PERMIT',
            'label' => 'Permis C',
            'issuedAt' => '2024-01-15',
            'expiresAt' => (new \DateTimeImmutable('+20 days'))->format('Y-m-d'),
            'fileUrl' => 'https://example.com/permit.pdf',
        ], 201);

        self::assertStringStartsWith('DD', (string) ($doc['id'] ?? ''));
        self::assertSame('PERMIT', $doc['type'] ?? null);

        $list = $this->api(
            'GET',
            '/api/agency/driver-documents?driver.id='.$driver->getId(),
            $ws['token'],
            null,
            200,
        );
        $members = $list['member'] ?? $list['hydra:member'] ?? (isset($list[0]) ? $list : null);
        self::assertIsArray($members);
        self::assertGreaterThanOrEqual(1, \count($members));
    }

    public function testAccountingMarginAndAlertsEndpoints(): void
    {
        $ws = $this->createPartnerWorkspace('AccRep');
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $payment = new AgencyPayment();
        $payment->setAgency($ws['agency']);
        $payment->setReference('ABP-MRG-'.strtoupper($this->suffix));
        $payment->setAmount(25000);
        $payment->setCurrency('CDF');
        $payment->setMethod(AgencyPayment::METHOD_CASH);
        $payment->setStatus(AgencyPayment::STATUS_PAID);
        $payment->setChannel(AgencyPayment::CHANNEL_POS);
        $payment->setProvider(AgencyPayment::PROVIDER_FLEXPAY);
        $payment->setPaidAt(new \DateTimeImmutable());
        $this->em->persist($payment);
        $this->em->flush();

        /** @var \App\Manager\AccountingAgencyManager $accounting */
        $accounting = static::getContainer()->get(\App\Manager\AccountingAgencyManager::class);
        $accounting->recordFromAgencyPayment($payment);

        $margin = $this->api(
            'GET',
            '/api/agency/accounting/reports/margin?from='.$today.'&to='.$today,
            $ws['token'],
            null,
            200,
        );
        self::assertSame($today, $margin['from'] ?? null);
        self::assertArrayHasKey('ca', $margin);
        self::assertArrayHasKey('passOnt', $margin);
        self::assertArrayHasKey('commission', $margin);
        self::assertArrayHasKey('net', $margin);
        self::assertGreaterThanOrEqual(0, $margin['ca'] ?? -1);

        $alerts = $this->api('GET', '/api/agency/accounting/alerts', $ws['token'], null, 200);
        self::assertArrayHasKey('cashVariances', $alerts);
        self::assertArrayHasKey('agedPendingPayments', $alerts);
        self::assertIsArray($alerts['cashVariances']);
        self::assertIsArray($alerts['agedPendingPayments']);
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
        self::assertSame(
            $expectedStatus,
            $this->client->getResponse()->getStatusCode(),
            sprintf('%s %s: %s', $method, $uri, $content),
        );

        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
