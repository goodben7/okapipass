<?php

namespace App\Tests\Functional\Agency;

use App\Entity\AgencyBaggageExcess;
use App\Entity\AgencyTicket;

final class BaggageAndManifestTest extends AgencyApiTestCase
{
    public function testRecordBaggageAndManifestReleaseNoShows(): void
    {
        $ws = $this->createPartnerWorkspace('BagManifest');
        $pastDate = (new \DateTimeImmutable('-1 day'))->format('Y-m-d');

        $this->api('PATCH', '/api/agency/offers/'.$ws['offer']->getId(), $ws['token'], [
            'baggageFreeKg' => 20,
            'baggageExcessPricePerKg' => 2500,
            'noShowReleaseMinutes' => 30,
        ]);

        $ticket = $this->api('POST', '/api/agency/tickets', $ws['token'], [
            'offer' => '/api/agency/offers/'.$ws['offer']->getId(),
            'passengerName' => 'Bagage Test',
            'passengerId' => 'CD-BAG-1',
            'passengerPhone' => '+243870000333',
            'seatNumber' => '02A',
            'travelDate' => $pastDate,
            'sendSms' => false,
        ], 201);
        $ticketId = $this->extractId($ticket, 'id') ?? $ticket['ticket']['id'] ?? null;
        if (null === $ticketId && isset($ticket['ticket']) && \is_array($ticket['ticket'])) {
            $ticketId = $ticket['ticket']['id'] ?? null;
        }
        self::assertNotNull($ticketId);

        $baggage = $this->api('POST', '/api/agency/tickets/'.$ticketId.'/baggage', $ws['token'], [
            'kg' => 35,
        ], 201);
        self::assertSame(37500, $baggage['amount'] ?? null);
        self::assertSame(15, $baggage['excessKg'] ?? null);
        self::assertSame(AgencyBaggageExcess::STATUS_RECORDED, $baggage['excess']['status'] ?? null);

        $manifest = $this->api(
            'GET',
            '/api/agency/manifests?offerId='.$ws['offer']->getId().'&travelDate='.$pastDate,
            $ws['token'],
        );
        self::assertSame($ws['offer']->getId(), $manifest['offerId'] ?? null);
        self::assertSame($pastDate, $manifest['travelDate'] ?? null);
        self::assertSame(1, $manifest['issuedCount'] ?? null);
        self::assertNotEmpty($manifest['tickets'] ?? null);

        $released = $this->api('POST', '/api/agency/manifests/release-noshows', $ws['token'], [
            'offerId' => $ws['offer']->getId(),
            'travelDate' => $pastDate,
        ]);
        self::assertSame(1, $released['releasedCount'] ?? null);

        $this->em->clear();
        $updatedTicket = $this->em->find(AgencyTicket::class, $ticketId);
        self::assertInstanceOf(AgencyTicket::class, $updatedTicket);
        self::assertSame(AgencyTicket::STATUS_NO_SHOW, $updatedTicket->getStatus());
        self::assertSame(35, $updatedTicket->getBaggageKg());

        $manifestAfter = $this->api(
            'GET',
            '/api/agency/manifests?offerId='.$ws['offer']->getId().'&travelDate='.$pastDate,
            $ws['token'],
        );
        self::assertSame(0, $manifestAfter['issuedCount'] ?? null);
        self::assertSame(1, $manifestAfter['noShowCount'] ?? null);
    }
}
