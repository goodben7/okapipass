<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyRentalContract;

final class AgencyRentalContractTest extends AgencyApiTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function rentalPayload(array $ws, string $startAt, string $endAt): array
    {
        return [
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'clientName' => 'Société Minex',
            'clientPhone' => '+243812345678',
            'clientCompany' => 'Minex SARL',
            'startAt' => $startAt,
            'endAt' => $endAt,
            'pickupLocation' => 'Kinshasa Gare Centrale',
            'dropoffLocation' => 'Matadi Port',
            'dailyRate' => 150000,
            'totalAmount' => 450000,
            'depositAmount' => 100000,
            'currency' => 'CDF',
            'notes' => 'Location charter 3 jours',
        ];
    }

    public function testCreateRentalContractInDraft(): void
    {
        $ws = $this->createPartnerWorkspace('RentalCreate');
        $date = $this->travelDate('+5 days');

        $created = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], $this->rentalPayload(
            $ws,
            $date.'T08:00:00',
            (new \DateTimeImmutable($date))->modify('+2 days')->format('Y-m-d').'T18:00:00',
        ), 201);

        self::assertSame(AgencyRentalContract::STATUS_DRAFT, $created['status'] ?? null);
        self::assertSame('Société Minex', $created['clientName'] ?? null);
        self::assertSame(450000, $created['totalAmount'] ?? null);
    }

    public function testConfirmedRentalBlocksBookingOnTravelDate(): void
    {
        $ws = $this->createPartnerWorkspace('RentalBlock');
        $travel = $this->travelDate('+4 days');

        $created = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], $this->rentalPayload(
            $ws,
            $travel.'T00:00:00',
            (new \DateTimeImmutable($travel))->modify('+1 day')->format('Y-m-d').'T23:59:59',
        ), 201);

        $confirmed = $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/confirm', $ws['token']);
        self::assertSame(AgencyRentalContract::STATUS_CONFIRMED, $confirmed['status'] ?? null);

        $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Blocked Passenger',
            'passengerId' => 'CD-800001',
            'passengerPhone' => '+243870000001',
            'seatNumber' => '01A',
            'travelDate' => $travel,
            'sendSms' => false,
        ], 422);
    }

    public function testBookingAllowedOutsideRentalPeriod(): void
    {
        $ws = $this->createPartnerWorkspace('RentalAllow');
        $rentedDay = $this->travelDate('+6 days');
        $openDay = (new \DateTimeImmutable($rentedDay))->modify('+5 days')->format('Y-m-d');

        $created = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], $this->rentalPayload(
            $ws,
            $rentedDay.'T08:00:00',
            (new \DateTimeImmutable($rentedDay))->modify('+1 day')->format('Y-m-d').'T20:00:00',
        ), 201);

        $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/confirm', $ws['token']);

        $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Allowed Passenger',
            'passengerId' => 'CD-800002',
            'passengerPhone' => '+243870000002',
            'seatNumber' => '01A',
            'travelDate' => $openDay,
            'sendSms' => false,
        ], 201);
    }

    public function testConfirmRejectsOverlappingRental(): void
    {
        $ws = $this->createPartnerWorkspace('RentalOverlap');
        $start = $this->travelDate('+7 days');
        $end = (new \DateTimeImmutable($start))->modify('+3 days')->format('Y-m-d');

        $first = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], $this->rentalPayload(
            $ws,
            $start.'T08:00:00',
            $end.'T18:00:00',
        ), 201);
        $this->api('POST', '/api/agency/rental-contracts/'.$first['id'].'/confirm', $ws['token']);

        $second = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], $this->rentalPayload(
            $ws,
            (new \DateTimeImmutable($start))->modify('+1 day')->format('Y-m-d').'T10:00:00',
            (new \DateTimeImmutable($start))->modify('+2 days')->format('Y-m-d').'T10:00:00',
        ), 201);

        $this->api('POST', '/api/agency/rental-contracts/'.$second['id'].'/confirm', $ws['token'], null, 409);
    }

    public function testRentalWorkflowActivateAndReturn(): void
    {
        $ws = $this->createPartnerWorkspace('RentalFlow');
        $date = $this->travelDate('+8 days');

        $created = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], $this->rentalPayload(
            $ws,
            $date.'T08:00:00',
            (new \DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d').'T18:00:00',
        ), 201);

        $confirmed = $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/confirm', $ws['token']);
        self::assertSame(AgencyRentalContract::STATUS_CONFIRMED, $confirmed['status'] ?? null);

        $active = $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/activate', $ws['token']);
        self::assertSame(AgencyRentalContract::STATUS_ACTIVE, $active['status'] ?? null);

        $returned = $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/return', $ws['token']);
        self::assertSame(AgencyRentalContract::STATUS_RETURNED, $returned['status'] ?? null);

        $this->em->clear();
        $reloaded = $this->api('GET', '/api/agency/rental-contracts/'.$created['id'], $ws['token']);
        self::assertSame(AgencyRentalContract::STATUS_RETURNED, $reloaded['status'] ?? null);
    }

    public function testCancelConfirmedRentalUnblocksBooking(): void
    {
        $ws = $this->createPartnerWorkspace('RentalCancel');
        $travel = $this->travelDate('+9 days');

        $created = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], $this->rentalPayload(
            $ws,
            $travel.'T00:00:00',
            (new \DateTimeImmutable($travel))->modify('+1 day')->format('Y-m-d').'T23:59:59',
        ), 201);

        $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/confirm', $ws['token']);
        $cancelled = $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/cancel', $ws['token']);
        self::assertSame(AgencyRentalContract::STATUS_CANCELLED, $cancelled['status'] ?? null);

        $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'After Cancel',
            'passengerId' => 'CD-800003',
            'passengerPhone' => '+243870000003',
            'seatNumber' => '01A',
            'travelDate' => $travel,
            'sendSms' => false,
        ], 201);
    }

    public function testTransportAvailabilityCalendarShowsRentalDays(): void
    {
        $ws = $this->createPartnerWorkspace('RentalCal');
        $from = $this->travelDate('+10 days');
        $to = (new \DateTimeImmutable($from))->modify('+4 days')->format('Y-m-d');
        $blocked = (new \DateTimeImmutable($from))->modify('+1 day')->format('Y-m-d');

        $created = $this->api('POST', '/api/agency/rental-contracts', $ws['token'], $this->rentalPayload(
            $ws,
            $blocked.'T00:00:00',
            (new \DateTimeImmutable($blocked))->modify('+1 day')->format('Y-m-d').'T23:59:59',
        ), 201);
        $this->api('POST', '/api/agency/rental-contracts/'.$created['id'].'/confirm', $ws['token']);

        $calendar = $this->api(
            'GET',
            sprintf(
                '/api/agency/transports/%s/availability?from=%s&to=%s',
                $ws['transport']->getId(),
                $from,
                $to,
            ),
            $ws['token'],
        );

        self::assertSame($ws['transport']->getId(), $calendar['transportId'] ?? null);
        self::assertSame($from, $calendar['from'] ?? null);
        self::assertSame($to, $calendar['to'] ?? null);

        $days = $calendar['days'] ?? [];
        self::assertIsArray($days);
        $byDate = [];
        foreach ($days as $day) {
            if (\is_array($day) && isset($day['date'])) {
                $byDate[$day['date']] = $day;
            }
        }

        self::assertFalse($byDate[$blocked]['available'] ?? true);
        self::assertSame('RENTAL', $byDate[$blocked]['reason'] ?? null);
        self::assertTrue($byDate[$from]['available'] ?? false);
    }
}
