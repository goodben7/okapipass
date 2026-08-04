<?php

namespace App\Tests\Functional\Agency;

use App\Domain\Agency\DeclarationCsvLimits;
use App\Entity\AgencyTransport;
use App\Entity\PassDeclaration;

/**
 * Spec §10 acceptance criteria AC-01 … AC-08.
 */
final class AgencyAcceptanceTest extends AgencyApiTestCase
{
    public function testAc01TwoBookingsSameSeatSecondGets409(): void
    {
        $ws = $this->createPartnerWorkspace('AC01');
        $travelDate = $this->travelDate();
        $body = [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Kabongo Jean',
            'passengerId' => 'CD-100001',
            'passengerPhone' => '+243810000001',
            'seatNumber' => '01A',
            'travelDate' => $travelDate,
            'status' => 'PENDING',
            'sendSms' => false,
        ];

        $first = $this->api('POST', '/api/agency/bookings', $ws['token'], $body, 201);
        self::assertNotNull($this->extractId($first));

        $this->api('POST', '/api/agency/bookings', $ws['token'], array_merge($body, [
            'passengerName' => 'Ilunga Sarah',
            'passengerId' => 'CD-100002',
            'passengerPhone' => '+243810000002',
        ]), 409);
    }

    public function testAc02BookingWithValidPassHasZeroPassPrice(): void
    {
        $ws = $this->createPartnerWorkspace('AC02');
        $this->seedIssuedPass('OP-TEST-AC02');

        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Kabongo Jean',
            'passengerId' => 'CD-200001',
            'passengerPhone' => '+243820000001',
            'seatNumber' => '01B',
            'travelDate' => $this->travelDate(),
            'okapiPassRef' => 'OP-TEST-AC02',
            'status' => 'CONFIRMED',
            'sendSms' => false,
        ], 201);

        $bookingId = $this->extractId($booking);
        self::assertNotNull($bookingId);

        $ticket = $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);
        self::assertSame(0, $ticket['passPrice'] ?? -1, 'Pass charge must be 0 for valid existing Pass');
        self::assertSame(85000, $ticket['ticketPrice'] ?? -1);
        self::assertTrue($ticket['hasExistingPass'] ?? $ticket['existingPass'] ?? false);
    }

    public function testAc03IssueTicketTwiceIsIdempotent(): void
    {
        $ws = $this->createPartnerWorkspace('AC03');
        $booking = $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Mbuyi Paul',
            'passengerId' => 'CD-300001',
            'passengerPhone' => '+243830000001',
            'seatNumber' => '01C',
            'travelDate' => $this->travelDate(),
            'sendSms' => false,
        ], 201);
        $bookingId = $this->extractId($booking);

        $t1 = $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);
        $t2 = $this->api('POST', '/api/agency/bookings/'.$bookingId.'/issue-ticket', $ws['token'], null, 201);

        self::assertSame($t1['id'] ?? null, $t2['id'] ?? null);
        self::assertSame($t1['reference'] ?? null, $t2['reference'] ?? null);
    }

    public function testAc04DeclareEmbarkationCreatesSubmittedDeclaration(): void
    {
        $ws = $this->createPartnerWorkspace('AC04');
        $travelDate = $this->travelDate();

        $ticket = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Nsimba Claire',
            'passengerId' => 'CD-400001',
            'passengerPhone' => '+243840000001',
            'seatNumber' => '02A',
            'travelDate' => $travelDate,
            'sendSms' => false,
        ], 201);
        $ticketId = $this->extractId($ticket) ?? ($ticket['ticket']['id'] ?? null);
        self::assertNotNull($ticketId);

        $embarkation = $this->api('POST', '/api/agency/embarkations', $ws['token'], [
            'label' => 'Départ AC04',
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'transport' => '/api/agency/transports/'.$ws['transport']->getId(),
            'departureDate' => $travelDate,
            'departureTime' => '06:00',
            'ticketIds' => [$ticketId],
        ], 201);
        $embarkationId = $embarkation['id'] ?? null;
        self::assertNotNull($embarkationId);

        $declaration = $this->api('POST', '/api/agency/embarkations/'.$embarkationId.'/declare', $ws['token'], null, 201);
        self::assertSame('submitted', $declaration['status'] ?? null);
        self::assertGreaterThan(0, $declaration['fptTotal'] ?? 0);

        // Second declare is idempotent
        $again = $this->api('POST', '/api/agency/embarkations/'.$embarkationId.'/declare', $ws['token'], null, 201);
        self::assertSame($declaration['id'] ?? null, $again['id'] ?? null);
    }

    public function testAc05CsvWithoutPassChargesFptPerLine(): void
    {
        $ws = $this->createPartnerWorkspace('AC05');
        $date = $this->travelDate();
        $csv = implode("\n", [
            'referenceBillet;date;passengerName;passengerId;origin;destination;ticketPrice;currency',
            "VP-CSV-1;{$date};Passager Un;CD-1;Kinshasa;Matadi;10000;CDF",
            "VP-CSV-2;{$date};Passager Deux;CD-2;Kinshasa;Matadi;10000;CDF",
        ]);

        $declaration = $this->api('POST', '/api/agency/declarations/import-csv', $ws['token'], [
            'content' => $csv,
            'label' => 'Import AC05',
        ], 201);

        self::assertSame(6000, $declaration['fptTotal'] ?? -1); // 2 × 3000
    }

    public function testImportCsvAcceptsMultipartFileUpload(): void
    {
        $ws = $this->createPartnerWorkspace('CSVFILE');
        $date = $this->travelDate();
        $csv = implode("\n", [
            'referenceBillet;date;passengerName;passengerId;origin;destination;ticketPrice;currency',
            "VP-CSV-FILE-1;{$date};Passager Fichier;CD-F-1;Kinshasa;Matadi;10000;CDF",
            "VP-CSV-FILE-2;{$date};Passager Fichier 2;CD-F-2;Kinshasa;Matadi;10000;CDF",
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, $csv);

        $this->client->request(
            'POST',
            '/api/agency/declarations/import-csv',
            parameters: ['label' => 'Import fichier'],
            files: [
                'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                    $tmp,
                    'import.csv',
                    'text/csv',
                    null,
                    true,
                ),
            ],
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$ws['token'],
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'multipart/form-data',
            ],
        );

        $content = $this->client->getResponse()->getContent() ?: '{}';
        self::assertSame(
            201,
            $this->client->getResponse()->getStatusCode(),
            sprintf('multipart import-csv failed: %s', $content)
        );

        $declaration = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(6000, $declaration['fptTotal'] ?? -1);
        self::assertSame(PassDeclaration::SOURCE_CSV, $declaration['source'] ?? null);
    }

    public function testImportCsvRejectsTooManyRows(): void
    {
        $ws = $this->createPartnerWorkspace('CSVMAX');
        $header = 'referenceBillet;date;passengerName;passengerId;origin;destination;ticketPrice;currency';
        $row = sprintf('VP-X;2026-08-05;Jean;CD-1;Kinshasa;Matadi;10000;CDF');
        // Keep payload small: parser counts lines before deep parse — build just over the limit.
        $lines = array_merge([$header], array_fill(0, DeclarationCsvLimits::MAX_ROWS + 1, $row));

        $this->api('POST', '/api/agency/declarations/import-csv', $ws['token'], [
            'content' => implode("\n", $lines),
            'label' => 'Too many rows',
        ], 422);
    }

    public function testAc06PartnerCannotReadOtherAgencyData(): void
    {
        $a = $this->createPartnerWorkspace('AC06A');
        $b = $this->createPartnerWorkspace('AC06B');

        $this->api('GET', '/api/agency/transports/'.$a['transport']->getId(), $a['token'], null, 200);
        $this->api('GET', '/api/agency/transports/'.$a['transport']->getId(), $b['token'], null, 404);

        $list = $this->api('GET', '/api/agency/transports', $b['token'], null, 200);
        $members = $list['member'] ?? $list['hydra:member'] ?? [];
        foreach ($members as $row) {
            self::assertNotSame($a['transport']->getId(), $row['id'] ?? null);
        }
    }

    public function testAc07MaintenanceTransportBlocksSale(): void
    {
        $ws = $this->createPartnerWorkspace('AC07');
        $ws['transport']->setStatus(AgencyTransport::STATUS_MAINTENANCE);
        $this->em->flush();

        $this->api('POST', '/api/agency/bookings', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Blocked',
            'passengerId' => 'CD-700001',
            'passengerPhone' => '+243870000001',
            'seatNumber' => '01A',
            'travelDate' => $this->travelDate(),
            'sendSms' => false,
        ], 422);
    }

    public function testAc08TicketReferenceFormatVpYearSeq(): void
    {
        $ws = $this->createPartnerWorkspace('AC08');
        $ticket = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Format Check',
            'passengerId' => 'CD-800001',
            'passengerPhone' => '+243880000001',
            'seatNumber' => '01D',
            'travelDate' => $this->travelDate(),
            'sendSms' => false,
        ], 201);

        $ref = $ticket['reference'] ?? ($ticket['ticket']['reference'] ?? null);
        self::assertNotNull($ref);
        self::assertMatchesRegularExpression('/^VP-\d{4}-\d{5}$/', $ref);

        $byRef = $this->api('GET', '/api/agency/tickets/by-reference/'.$ref, $ws['token'], null, 200);
        self::assertSame($ref, $byRef['reference'] ?? null);
    }

    public function testNotificationPreviewForTicket(): void
    {
        $ws = $this->createPartnerWorkspace('NOTIF');
        $ticket = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Notif Preview',
            'passengerId' => 'CD-900001',
            'passengerPhone' => '+243890000001',
            'seatNumber' => '01A',
            'travelDate' => $this->travelDate(),
            'sendSms' => false,
        ], 201);

        $ticketId = $this->extractId($ticket);
        self::assertNotNull($ticketId);

        $preview = $this->api('POST', '/api/agency/notifications/preview', $ws['token'], [
            'type' => 'ticket',
            'targetId' => $ticketId,
        ], 201);

        $ref = $ticket['reference'] ?? ($ticket['ticket']['reference'] ?? '');
        self::assertNotEmpty($preview['smsText'] ?? null);
        self::assertNotEmpty($preview['whatsappUrl'] ?? null);
        self::assertStringContainsString((string) $ref, (string) ($preview['smsText'] ?? ''));
    }
}