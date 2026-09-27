<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyOffer;
use App\Entity\SchoolAttendance;
use App\Entity\SchoolContract;

final class SchoolBusTest extends AgencyApiTestCase
{
    public function testSchoolOfferForcesOfflineAndHiddenFromPublicCatalog(): void
    {
        $ws = $this->createPartnerWorkspace('SchoolOff');

        $offer = $this->api('POST', '/api/agency/offers', $ws['token'], [
            'label' => 'Navette scolaire',
            'origin' => 'Kimwenza',
            'destination' => 'Lycée',
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'ticketPrice' => 0,
            'currency' => 'CDF',
            'departureTime' => '06:30',
            'durationMinutes' => 40,
            'serviceType' => AgencyOffer::SERVICE_SCHOOL,
            'onlineSales' => true,
            'active' => true,
        ], 201);

        self::assertSame(AgencyOffer::SERVICE_SCHOOL, $offer['serviceType'] ?? null);
        self::assertFalse($offer['onlineSales'] ?? true);

        $offerId = (string) ($offer['id'] ?? '');
        self::assertNotSame('', $offerId);

        /** @var \App\Repository\AgencyOfferRepository $repo */
        $repo = static::getContainer()->get(\App\Repository\AgencyOfferRepository::class);
        self::assertNull($repo->findPublicOnlineById($offerId));

        $this->client->request(
            'GET',
            '/api/public/agency/offers?agencyId='.$ws['agency']->getId(),
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true);
        self::assertIsArray($payload);
        $ids = array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $this->collectionMembers($payload),
        );
        self::assertNotContains($offerId, $ids);
    }

    public function testCreateSchoolContractAndStudent(): void
    {
        $ws = $this->createPartnerWorkspace('SchoolCtr');
        $schoolOffer = $this->createSchoolOffer($ws);

        $contract = $this->api('POST', '/api/agency/school-contracts', $ws['token'], [
            'schoolName' => 'Lycée Test '.$this->suffix,
            'schoolPhone' => '+243810011223',
            'offer' => '/api/agency/offers/'.$schoolOffer['id'],
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'startDate' => '2026-09-01',
            'endDate' => '2027-06-30',
            'status' => SchoolContract::STATUS_ACTIVE,
            'monthlyFee' => 120000,
            'currency' => 'CDF',
            'stops' => [
                ['code' => 'STOP1', 'label' => 'Carrefour', 'order' => 1, 'time' => '06:15'],
                ['code' => 'SCHOOL', 'label' => 'Lycée', 'order' => 2, 'time' => '07:00'],
            ],
        ], 201);

        self::assertStringStartsWith('SK', (string) ($contract['id'] ?? ''));
        self::assertSame(SchoolContract::STATUS_ACTIVE, $contract['status'] ?? null);
        self::assertSame('Lycée Test '.$this->suffix, $contract['schoolName'] ?? null);

        $student = $this->api('POST', '/api/agency/school-students', $ws['token'], [
            'contract' => '/api/agency/school-contracts/'.$contract['id'],
            'fullName' => 'Kabongo Junior',
            'phone' => '+243820033445',
            'grade' => '6ème',
            'pickupStopCode' => 'STOP1',
            'dropoffStopCode' => 'SCHOOL',
            'active' => true,
        ], 201);

        self::assertStringStartsWith('SU', (string) ($student['id'] ?? ''));
        self::assertSame('Kabongo Junior', $student['fullName'] ?? null);
        self::assertTrue($student['active'] ?? false);
    }

    public function testRecordAttendancePresent(): void
    {
        $ws = $this->createPartnerWorkspace('SchoolAtt');
        $ctx = $this->seedContractWithStudent($ws);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $attendance = $this->api('POST', '/api/agency/school/attendance', $ws['token'], [
            'contractId' => $ctx['contractId'],
            'date' => $today,
            'studentId' => $ctx['studentId'],
            'status' => SchoolAttendance::STATUS_PRESENT,
        ], 200);

        self::assertStringStartsWith('SA', (string) ($attendance['id'] ?? ''));
        self::assertSame(SchoolAttendance::STATUS_PRESENT, $attendance['status'] ?? null);
    }

    public function testRosterReturnsActiveStudent(): void
    {
        $ws = $this->createPartnerWorkspace('SchoolRos');
        $ctx = $this->seedContractWithStudent($ws);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->api('POST', '/api/agency/school/attendance', $ws['token'], [
            'contractId' => $ctx['contractId'],
            'date' => $today,
            'studentId' => $ctx['studentId'],
            'status' => SchoolAttendance::STATUS_PRESENT,
        ], 200);

        $roster = $this->api(
            'GET',
            '/api/agency/school/roster?contractId='.$ctx['contractId'].'&date='.$today,
            $ws['token'],
            null,
            200,
        );

        self::assertSame($ctx['contractId'], $roster['contractId'] ?? null);
        self::assertSame($today, $roster['date'] ?? null);
        self::assertIsArray($roster['students'] ?? null);
        self::assertCount(1, $roster['students']);
        self::assertSame($ctx['studentId'], $roster['students'][0]['id'] ?? null);
        self::assertSame(SchoolAttendance::STATUS_PRESENT, $roster['students'][0]['attendanceStatus'] ?? null);
    }

    public function testIntercityOfferStillPublicWhenOnlineSalesTrue(): void
    {
        $ws = $this->createPartnerWorkspace('SchoolPub');

        $offer = $this->api('POST', '/api/agency/offers', $ws['token'], [
            'label' => 'Kin-Matadi Express',
            'origin' => 'PublicOrigin'.$this->suffix,
            'destination' => 'Matadi',
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'ticketPrice' => 50000,
            'currency' => 'CDF',
            'departureTime' => '07:00',
            'durationMinutes' => 180,
            'serviceType' => AgencyOffer::SERVICE_INTERCITY,
            'onlineSales' => true,
            'active' => true,
        ], 201);

        self::assertSame(AgencyOffer::SERVICE_INTERCITY, $offer['serviceType'] ?? null);
        self::assertTrue($offer['onlineSales'] ?? false);

        $offerId = (string) ($offer['id'] ?? '');
        /** @var \App\Repository\AgencyOfferRepository $repo */
        $repo = static::getContainer()->get(\App\Repository\AgencyOfferRepository::class);
        self::assertNotNull($repo->findPublicOnlineById($offerId));

        $this->client->request(
            'GET',
            '/api/public/agency/offers?agencyId='.$ws['agency']->getId().'&origin=PublicOrigin'.$this->suffix,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true);
        self::assertIsArray($payload);
        $ids = array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $this->collectionMembers($payload),
        );
        self::assertContains($offerId, $ids);
    }

    /**
     * @param array{token: string, transport: \App\Entity\AgencyTransport} $ws
     *
     * @return array{id: string}
     */
    private function createSchoolOffer(array $ws): array
    {
        return $this->api('POST', '/api/agency/offers', $ws['token'], [
            'label' => 'Offer scolaire '.$this->suffix,
            'origin' => 'Quartier',
            'destination' => 'École',
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'ticketPrice' => 0,
            'currency' => 'CDF',
            'departureTime' => '06:00',
            'durationMinutes' => 30,
            'serviceType' => AgencyOffer::SERVICE_SCHOOL,
            'active' => true,
        ], 201);
    }

    /**
     * @param array{token: string, transport: \App\Entity\AgencyTransport} $ws
     *
     * @return array{contractId: string, studentId: string}
     */
    private function seedContractWithStudent(array $ws): array
    {
        $schoolOffer = $this->createSchoolOffer($ws);
        $contract = $this->api('POST', '/api/agency/school-contracts', $ws['token'], [
            'schoolName' => 'École '.$this->suffix,
            'offer' => '/api/agency/offers/'.$schoolOffer['id'],
            'startDate' => '2026-09-01',
            'endDate' => '2027-06-30',
            'status' => SchoolContract::STATUS_ACTIVE,
            'monthlyFee' => 0,
            'stops' => [
                ['code' => 'A', 'label' => 'Arrêt A', 'order' => 1],
                ['code' => 'B', 'label' => 'École', 'order' => 2],
            ],
        ], 201);

        $student = $this->api('POST', '/api/agency/school-students', $ws['token'], [
            'contract' => '/api/agency/school-contracts/'.$contract['id'],
            'fullName' => 'Élève Test',
            'pickupStopCode' => 'A',
            'dropoffStopCode' => 'B',
            'active' => true,
        ], 201);

        return [
            'contractId' => (string) $contract['id'],
            'studentId' => (string) $student['id'],
        ];
    }
}
