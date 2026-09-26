<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Entity\CashHandover;
use App\Entity\TravelerPass;
use App\Entity\TravelerPassProduct;
use App\Entity\User;

final class PartialGapsBacklogTest extends AgencyApiTestCase
{
    public function testTravelerProfilePatchIdDocument(): void
    {
        $token = $this->travelerToken('+2438400'.random_int(100000, 999999));

        $patched = $this->api('PATCH', '/api/traveler/me', $token, [
            'idDocument' => 'CD-ID-'.strtoupper($this->suffix),
            'emergencyContactName' => 'Contact Urgence',
            'emergencyContactPhone' => '+2438500112233',
        ], 200);

        self::assertSame('CD-ID-'.strtoupper($this->suffix), $patched['idDocument'] ?? null);
        self::assertSame('Contact Urgence', $patched['emergencyContactName'] ?? null);
        self::assertSame('+2438500112233', $patched['emergencyContactPhone'] ?? null);

        $me = $this->api('GET', '/api/traveler/me', $token, null, 200);
        self::assertSame('CD-ID-'.strtoupper($this->suffix), $me['idDocument'] ?? null);
    }

    public function testTravelerTicketShareReturnsShareUrl(): void
    {
        $phone = '+2438411'.random_int(100000, 999999);
        $ws = $this->createPartnerWorkspace('ShareV7');
        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-Share',
            'deviceId' => 'dev-share-1',
        ], 201);
        self::assertSame('dev-share-1', $session['deviceId'] ?? null);

        $sale = $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Share Client',
            'passengerId' => 'CD-SH-1',
            'passengerPhone' => $phone,
            'seatNumber' => '02A',
            'travelDate' => $this->travelDate('+4 days'),
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 201);

        $travelerToken = $this->travelerToken($phone);
        $share = $this->api('POST', '/api/traveler/tickets/'.$sale['ticketId'].'/share', $travelerToken, [
            'toPhone' => '+2438422'.random_int(100000, 999999),
        ], 200);

        self::assertNotEmpty($share['shareUrl'] ?? null);
        self::assertNotEmpty($share['whatsappUrl'] ?? null);
        self::assertStringContainsString('/api/public/tickets/share/', (string) ($share['shareUrl'] ?? ''));
        self::assertStringContainsString('wa.me', (string) ($share['whatsappUrl'] ?? ''));
    }

    public function testTravelerPassListAndConsumeOnQr(): void
    {
        $phone = '+2438433'.random_int(100000, 999999);
        $ws = $this->createPartnerWorkspace('PassV7');

        $product = new TravelerPassProduct();
        $product->setAgency($ws['agency']);
        $product->setCode('PASS'.strtoupper($this->suffix));
        $product->setLabel('Forfait 5');
        $product->setTripsAllowed(5);
        $product->setValidityDays(30);
        $product->setPrice(50000);
        $product->setCurrency('CDF');
        $product->setActive(true);
        $this->em->persist($product);
        $this->em->flush();
        $productId = $product->getId();
        $agencyId = $ws['agency']->getId();
        $offerId = $ws['offer']->getId();

        $travelerToken = $this->travelerToken($phone);
        $this->em->clear();

        /** @var User|null $traveler */
        $traveler = $this->em->getRepository(User::class)->findOneBy(['phone' => $phone]);
        if (!$traveler instanceof User) {
            $traveler = $this->em->getRepository(User::class)->createQueryBuilder('u')
                ->andWhere('u.phone LIKE :p')
                ->setParameter('p', '%'.substr($phone, -9))
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }
        self::assertInstanceOf(User::class, $traveler);

        $agency = $this->em->getRepository(\App\Entity\Agency::class)->find($agencyId);
        $product = $this->em->getRepository(TravelerPassProduct::class)->find($productId);
        self::assertNotNull($agency);
        self::assertNotNull($product);

        $pass = new TravelerPass();
        $pass->setUser($traveler);
        $pass->setProduct($product);
        $pass->setAgency($agency);
        $pass->setStatus(TravelerPass::STATUS_ACTIVE);
        $pass->setTripsRemaining(3);
        $pass->setValidFrom(new \DateTimeImmutable('today'));
        $pass->setValidTo(new \DateTimeImmutable('+29 days'));
        $pass->setPurchasePrice(50000);
        $pass->setCurrency('CDF');
        $this->em->persist($pass);
        $this->em->flush();

        $list = $this->api('GET', '/api/traveler/passes', $travelerToken, null, 200);
        $members = $this->collectionMembers($list);
        self::assertNotEmpty($members);
        self::assertStringStartsWith('TP', (string) ($members[0]['id'] ?? ''));

        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-Pass',
        ], 201);
        $sale = $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$offerId,
            'passengerName' => 'Pass Client',
            'passengerId' => 'CD-PS-1',
            'passengerPhone' => $phone,
            'seatNumber' => '02B',
            'travelDate' => $this->travelDate('+5 days'),
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 201);

        $this->em->clear();
        /** @var AgencyTicket $ticket */
        $ticket = $this->em->getRepository(AgencyTicket::class)->find($sale['ticketId']);
        self::assertInstanceOf(AgencyTicket::class, $ticket);
        $pass = $this->em->getRepository(TravelerPass::class)->find($pass->getId());
        self::assertInstanceOf(TravelerPass::class, $pass);
        $ticket->setTravelerPass($pass);
        if (null === $ticket->getQrToken()) {
            $ticket->setQrToken(bin2hex(random_bytes(16)));
            $ticket->setQrTokenExpiresAt(new \DateTimeImmutable('+2 hours'));
        }
        $this->em->flush();
        $qrToken = $ticket->getQrToken();
        $passId = $pass->getId();

        $this->api('POST', '/api/agency/tickets/validate-qr', $ws['token'], [
            'token' => $qrToken,
        ], 200);

        $this->em->clear();
        /** @var TravelerPass $reloaded */
        $reloaded = $this->em->getRepository(TravelerPass::class)->find($passId);
        self::assertSame(2, $reloaded->getTripsRemaining());
    }

    public function testCashHandoverResolve(): void
    {
        $ws = $this->createPartnerWorkspace('CashRes');
        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-CR',
            'deviceId' => 'pos-device-1',
        ], 201);

        $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Cash Client',
            'passengerId' => 'CD-CR-1',
            'passengerPhone' => '+2438444'.random_int(100000, 999999),
            'seatNumber' => '01C',
            'travelDate' => $this->travelDate('+6 days'),
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 201);

        $handover = $this->api('POST', '/api/agency/pos/cash-handovers', $ws['token'], [
            'session' => $session['id'],
            'declaredAmount' => 1,
            'notes' => 'short',
        ], 201);

        $confirmed = $this->api('POST', '/api/agency/pos/cash-handovers/'.$handover['id'].'/confirm', $ws['token'], null, 200);
        self::assertSame(CashHandover::STATUS_CONFIRMED, $confirmed['status'] ?? null);
        self::assertNotSame(0, $confirmed['variance'] ?? 0);

        $resolved = $this->api('POST', '/api/agency/pos/cash-handovers/'.$handover['id'].'/resolve', $ws['token'], [
            'resolutionJustification' => 'Erreur de caisse corrigée',
        ], 200);
        self::assertSame(CashHandover::STATUS_RESOLVED, $resolved['status'] ?? null);
        self::assertSame('Erreur de caisse corrigée', $resolved['resolutionJustification'] ?? null);
    }

    public function testCancelRateSpikeOrDashboardFields(): void
    {
        $ws = $this->createPartnerWorkspace('DashV7');

        $alerts = $this->api('GET', '/api/agency/accounting/alerts', $ws['token'], null, 200);
        self::assertArrayHasKey('cashVariances', $alerts);
        // cancelRateSpike may be null (omitted) when no tickets; structure present when computed
        if (array_key_exists('cancelRateSpike', $alerts) && null !== $alerts['cancelRateSpike']) {
            self::assertIsArray($alerts['cancelRateSpike']);
            self::assertArrayHasKey('rate', $alerts['cancelRateSpike']);
        }

        $dash = $this->api('GET', '/api/agency/dashboard', $ws['token'], null, 200);
        self::assertArrayHasKey('cashRiskCount', $dash);
        self::assertArrayHasKey('cancelRate7d', $dash);
        self::assertIsInt($dash['cashRiskCount']);
    }

    public function testCostPerKmLitersOrPunctuality(): void
    {
        $ws = $this->createPartnerWorkspace('FleetV7');
        $from = (new \DateTimeImmutable('-7 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable('+1 day'))->format('Y-m-d');

        $cost = $this->api(
            'GET',
            '/api/agency/fleet/reports/cost-per-km?transport='.$ws['transport']->getId().'&from='.$from.'&to='.$to,
            $ws['token'],
            null,
            200,
        );
        self::assertArrayHasKey('litersPer100Km', $cost);

        $punct = $this->api(
            'GET',
            '/api/agency/fleet/reports/punctuality?from='.$from.'&to='.$to,
            $ws['token'],
            null,
            200,
        );
        self::assertArrayHasKey('rows', $punct);
        self::assertIsArray($punct['rows']);
    }

    public function testInsuranceAttestationWithoutInsuranceReturns422(): void
    {
        $ws = $this->createPartnerWorkspace('InsV7');
        $session = $this->api('POST', '/api/agency/pos/sessions/open', $ws['token'], [
            'pointOfSale' => 'Guichet-Ins',
        ], 201);
        $sale = $this->api('POST', '/api/agency/pos/sales', $ws['token'], [
            'session' => $session['id'],
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'No Ins',
            'passengerId' => 'CD-IN-1',
            'passengerPhone' => '+2438455'.random_int(100000, 999999),
            'seatNumber' => '01D',
            'travelDate' => $this->travelDate('+7 days'),
            'method' => AgencyPayment::METHOD_CASH,
            'sendSms' => false,
        ], 201);

        $this->client->request(
            'GET',
            '/api/agency/tickets/'.$sale['ticketId'].'/insurance-attestation',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$ws['token'],
                'HTTP_ACCEPT' => 'application/pdf',
            ],
        );
        self::assertSame(
            422,
            $this->client->getResponse()->getStatusCode(),
            $this->client->getResponse()->getContent() ?: '',
        );
    }

    private function travelerToken(string $phone): string
    {
        $otp = $this->publicApi('POST', '/api/public/auth/otp/request', ['phone' => $phone], 200);
        $verified = $this->publicApi('POST', '/api/public/auth/otp/verify', [
            'phone' => $phone,
            'code' => $otp['debugCode'],
        ], 200);
        $token = $verified['token'] ?? null;
        self::assertNotEmpty($token);

        return (string) $token;
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
