<?php

namespace App\Tests\Functional\Ont;

use App\Entity\PassDeclaration;
use App\Entity\Profile;
use App\Entity\User;
use App\Model\UserProxyIntertace;
use App\Tests\Functional\Agency\AgencyApiTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OntDashboardTest extends AgencyApiTestCase
{
    public function testOntDashboardRequiresAuth(): void
    {
        $this->client->request('GET', '/api/ont/dashboard', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testPartnerCannotAccessOntDashboard(): void
    {
        $ws = $this->createPartnerWorkspace('OntDashPartner');
        $this->api('GET', '/api/ont/dashboard', $ws['token'], null, 403);
    }

    public function testOntAdminCanReadDashboard(): void
    {
        $ont = $this->createOntUser(UserProxyIntertace::PERSON_ONT_ADMIN);
        $ws = $this->createPartnerWorkspace('OntDashData');
        $yearMonth = (new \DateTimeImmutable('+5 days'))->format('Y-m');
        $travelDate = $this->travelDate('+5 days');

        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Jean Ont',
            'passengerId' => 'CD-ONT-1',
            'passengerPhone' => '+243810011111',
            'seatNumber' => '01A',
            'travelDate' => $travelDate,
            'status' => 'CONFIRMED',
            'sendSms' => false,
        ], 201);
        $bookingId = $this->extractId($booking);
        self::assertNotNull($bookingId);
        $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);

        $declaration = $this->api('POST', '/api/agency/declarations/generate-monthly', $ws['token'], [
            'yearMonth' => $yearMonth,
        ], 201);
        self::assertSame(PassDeclaration::SOURCE_MONTHLY, $declaration['source'] ?? null);

        $body = $this->api('GET', '/api/ont/dashboard?periodMonth='.$yearMonth, $ont['token'], null, 200);

        self::assertSame('ont-dashboard', $body['id'] ?? null);
        self::assertSame($yearMonth, $body['periodMonth'] ?? null);
        self::assertArrayHasKey('kpis', $body);
        self::assertGreaterThanOrEqual(1, $body['kpis']['agenciesActive'] ?? 0);
        self::assertGreaterThanOrEqual(1, $body['kpis']['ticketsMonth'] ?? 0);
        self::assertGreaterThanOrEqual(3000, $body['kpis']['fptDue'] ?? 0);
        self::assertArrayHasKey('recentDeclarations', $body);
        self::assertArrayHasKey('topAgenciesByFptDue', $body);
        self::assertArrayHasKey('alerts', $body);
        self::assertArrayHasKey('fptByMonth', $body);
        self::assertSame(15, $body['pollSuggestedSeconds'] ?? null);
        self::assertNotEmpty($body['generatedAt'] ?? null);
    }

    public function testOntAgentCanReadDashboard(): void
    {
        $ont = $this->createOntUser(UserProxyIntertace::PERSON_ONT_AGENT);
        $body = $this->api('GET', '/api/ont/dashboard', $ont['token'], null, 200);
        self::assertSame('ont-dashboard', $body['id'] ?? null);
        self::assertArrayHasKey('kpis', $body);
    }

    /**
     * @return array{token: string, email: string}
     */
    private function createOntUser(string $personType): array
    {
        $email = sprintf('ont_%s_%s@ont.test', strtolower($personType), $this->suffix);
        $password = 'OntPass123!';

        $profile = $this->em->getRepository(Profile::class)->findOneBy(['personType' => $personType]);
        if (null === $profile) {
            $profile = new Profile();
            $profile->setLabel($personType);
            $profile->setPersonType($personType);
            $profile->setPermission([$personType === UserProxyIntertace::PERSON_ONT_ADMIN ? 'ROLE_ONT_ADMIN' : 'ROLE_ONT_AGENT']);
            $profile->setActive(true);
            $this->em->persist($profile);
            $this->em->flush();
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('ONT '.$personType);
        $user->setPhone('+2438999'.random_int(100000, 999999));
        $user->setProfile($profile);
        $user->setPersonType($personType);
        $user->setPlainPassword($password);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setCreatedAt(new \DateTimeImmutable('now'));
        $this->em->persist($user);
        $this->em->flush();

        return [
            'token' => $this->authenticate($email, $password),
            'email' => $email,
        ];
    }
}
