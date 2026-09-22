<?php

namespace App\Tests\Functional\Ont;

use App\Entity\PassDeclaration;
use App\Entity\Profile;
use App\Entity\User;
use App\Model\UserProxyIntertace;
use App\Tests\Functional\Agency\AgencyApiTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OntAgencyDeclarationsAccessTest extends AgencyApiTestCase
{
    public function testOntAdminCanListAndSummarizeDeclarations(): void
    {
        $ws = $this->createPartnerWorkspace('OntDeclList');
        $yearMonth = (new \DateTimeImmutable('+6 days'))->format('Y-m');
        $travelDate = $this->travelDate('+6 days');

        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Ont Decl',
            'passengerId' => 'CD-ONT-DECL',
            'passengerPhone' => '+243810022222',
            'seatNumber' => '01B',
            'travelDate' => $travelDate,
            'status' => 'CONFIRMED',
            'sendSms' => false,
        ], 201);
        $bookingId = $this->extractId($booking);
        self::assertNotNull($bookingId);
        $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);

        $created = $this->api('POST', '/api/agency/declarations/generate-monthly', $ws['token'], [
            'yearMonth' => $yearMonth,
        ], 201);
        self::assertSame(PassDeclaration::SOURCE_MONTHLY, $created['source'] ?? null);
        $declarationId = $created['id'] ?? null;
        self::assertNotNull($declarationId);

        $ont = $this->createOntUser(UserProxyIntertace::PERSON_ONT_ADMIN);

        $list = $this->api('GET', '/api/agency/declarations', $ont['token'], null, 200);
        $member = $list['member'] ?? $list['hydra:member'] ?? (array_is_list($list) ? $list : []);
        self::assertNotEmpty($member);
        $ids = array_column($member, 'id');
        self::assertContains($declarationId, $ids);

        $filtered = $this->api(
            'GET',
            '/api/agency/declarations?agency.id='.$ws['agency']->getId(),
            $ont['token'],
            null,
            200,
        );
        $filteredMember = $filtered['member'] ?? $filtered['hydra:member'] ?? (array_is_list($filtered) ? $filtered : []);
        self::assertNotEmpty($filteredMember);

        $item = $this->api('GET', '/api/agency/declarations/'.$declarationId, $ont['token'], null, 200);
        self::assertSame($declarationId, $item['id'] ?? null);
        self::assertSame($ws['agency']->getId(), $item['agency']['id'] ?? null);

        $summaryAll = $this->api('GET', '/api/agency/declarations/summary', $ont['token'], null, 200);
        self::assertArrayHasKey('fptDue', $summaryAll);
        self::assertGreaterThanOrEqual(0, $summaryAll['fptDue']);

        $summaryOne = $this->api(
            'GET',
            '/api/agency/declarations/summary?agencyId='.$ws['agency']->getId(),
            $ont['token'],
            null,
            200,
        );
        self::assertGreaterThanOrEqual(3000, $summaryOne['fptDue'] ?? 0);
    }

    /**
     * @return array{token: string, email: string}
     */
    private function createOntUser(string $personType): array
    {
        $email = sprintf('ont_decl_%s_%s@ont.test', strtolower($personType), $this->suffix);
        $password = 'OntPass123!';

        $profile = $this->em->getRepository(Profile::class)->findOneBy(['personType' => $personType]);
        if (null === $profile) {
            $profile = new Profile();
            $profile->setLabel($personType);
            $profile->setPersonType($personType);
            $profile->setPermission(['ROLE_ONT_ADMIN']);
            $profile->setActive(true);
            $this->em->persist($profile);
            $this->em->flush();
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('ONT Decl');
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
