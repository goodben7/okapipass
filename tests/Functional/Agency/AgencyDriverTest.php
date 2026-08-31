<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyDriver;
use App\Entity\AgencyEmbarkation;

final class AgencyDriverTest extends AgencyApiTestCase
{
    public function testCreateAndListDrivers(): void
    {
        $ws = $this->createPartnerWorkspace('Drivers');

        $created = $this->api('POST', '/api/agency/drivers', $ws['token'], [
            'fullName' => 'Jean Kabongo',
            'phone' => '+243812345678',
            'licenseNumber' => 'LIC-'.strtoupper($this->suffix),
            'licenseExpiresAt' => '2027-12-31',
            'status' => AgencyDriver::STATUS_ACTIVE,
            'notes' => 'Senior driver',
        ], 201);

        self::assertSame('Jean Kabongo', $created['fullName'] ?? null);
        self::assertSame(AgencyDriver::STATUS_ACTIVE, $created['status'] ?? null);
        self::assertNotEmpty($created['id'] ?? null);

        $list = $this->api(
            'GET',
            '/api/agency/drivers?licenseNumber='.urlencode('LIC-'.strtoupper($this->suffix)),
            $ws['token'],
            null,
            200,
        );
        $members = $this->collectionMembers($list);
        self::assertContains(
            $created['id'],
            array_map(static fn (array $row): string => (string) ($row['id'] ?? ''), $members),
        );
    }

    public function testDuplicateLicenseReturns409(): void
    {
        $ws = $this->createPartnerWorkspace('DriverDup');
        $license = 'DUP-'.strtoupper($this->suffix);
        $payload = [
            'fullName' => 'Driver One',
            'phone' => '+243810000001',
            'licenseNumber' => $license,
        ];

        $this->api('POST', '/api/agency/drivers', $ws['token'], $payload, 201);
        $this->api('POST', '/api/agency/drivers', $ws['token'], array_merge($payload, [
            'fullName' => 'Driver Two',
            'phone' => '+243810000002',
        ]), 409);
    }

    public function testPartnerCannotReadOtherAgencyDriver(): void
    {
        $a = $this->createPartnerWorkspace('DriverA');
        $b = $this->createPartnerWorkspace('DriverB');

        $created = $this->api('POST', '/api/agency/drivers', $a['token'], [
            'fullName' => 'Private Driver',
            'phone' => '+243811111111',
            'licenseNumber' => 'PRIV-'.strtoupper($this->suffix),
        ], 201);

        $this->api('GET', '/api/agency/drivers/'.$created['id'], $a['token'], null, 200);
        $this->api('GET', '/api/agency/drivers/'.$created['id'], $b['token'], null, 404);
    }

    public function testEmbarkationCanAssignActiveDriver(): void
    {
        $ws = $this->createPartnerWorkspace('EmbDriver');
        $driver = $this->api('POST', '/api/agency/drivers', $ws['token'], [
            'fullName' => 'Paul Mwamba',
            'phone' => '+243822222222',
            'licenseNumber' => 'EMB-'.strtoupper($this->suffix),
        ], 201);

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Matadi morning',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'driver' => '/api/agency/drivers/'.$driver['id'],
            'departureDate' => $this->travelDate('+5 days'),
            'departureTime' => '06:00',
        ], 201);

        self::assertSame($driver['id'], $embarkation['driver']['id'] ?? null);
        self::assertSame('Paul Mwamba', $embarkation['driver']['fullName'] ?? null);
    }

    public function testInactiveDriverCannotBeAssignedToEmbarkation(): void
    {
        $ws = $this->createPartnerWorkspace('InactiveDriver');
        $driver = $this->api('POST', '/api/agency/drivers', $ws['token'], [
            'fullName' => 'Inactive Driver',
            'phone' => '+243833333333',
            'licenseNumber' => 'INA-'.strtoupper($this->suffix),
            'status' => AgencyDriver::STATUS_INACTIVE,
        ], 201);

        $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Should fail',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'driver' => '/api/agency/drivers/'.$driver['id'],
            'departureDate' => $this->travelDate('+6 days'),
            'departureTime' => '06:00',
        ], 422);
    }

    public function testFilterEmbarkationsByDriver(): void
    {
        $ws = $this->createPartnerWorkspace('FilterDriver');
        $driver = $this->api('POST', '/api/agency/drivers', $ws['token'], [
            'fullName' => 'Filter Driver',
            'phone' => '+243844444444',
            'licenseNumber' => 'FIL-'.strtoupper($this->suffix),
        ], 201);

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'With driver',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'driver' => '/api/agency/drivers/'.$driver['id'],
            'departureDate' => $this->travelDate('+7 days'),
            'departureTime' => '06:00',
        ], 201);

        $filtered = $this->api(
            'GET',
            '/api/agency/embarkations?driver.id='.$driver['id'],
            $ws['token'],
            null,
            200,
        );
        $members = $this->collectionMembers($filtered);
        self::assertCount(1, $members);
        self::assertSame(AgencyEmbarkation::STATUS_PLANNED, $members[0]['status'] ?? null);
    }

    public function testCannotDeleteDriverReferencedByEmbarkation(): void
    {
        $ws = $this->createPartnerWorkspace('DeleteDriver');
        $driver = $this->api('POST', '/api/agency/drivers', $ws['token'], [
            'fullName' => 'Locked Driver',
            'phone' => '+243855555555',
            'licenseNumber' => 'DEL-'.strtoupper($this->suffix),
        ], 201);

        $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Blocks delete',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'driver' => '/api/agency/drivers/'.$driver['id'],
            'departureDate' => $this->travelDate('+8 days'),
            'departureTime' => '06:00',
        ], 201);

        $this->api('DELETE', '/api/agency/drivers/'.$driver['id'], $ws['token'], null, 409);
    }
}
