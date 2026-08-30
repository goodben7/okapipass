<?php

namespace App\Tests\Functional\PublicAgency;

use App\Entity\AgencyOffer;
use App\Tests\Functional\Agency\AgencyApiTestCase;

final class PublicAgencyCatalogTest extends AgencyApiTestCase
{
    public function testListOffersReturnsOnlyOnlineSales(): void
    {
        $online = $this->createPartnerWorkspace('OnlineCatalog');
        $offline = $this->createPartnerWorkspace('OfflineCatalog');

        $online['offer']->setOrigin('CatalogOrigin'.$this->suffix);
        $this->enableOnlineSales($online['offer']);

        $offerId = (string) $online['offer']->getId();
        $agencyId = (string) $online['agency']->getId();

        /** @var \App\Repository\AgencyOfferRepository $repo */
        $repo = static::getContainer()->get(\App\Repository\AgencyOfferRepository::class);
        self::assertNotNull($repo->findPublicOnlineById($offerId));

        $this->publicGet('/api/public/agency/offers?agencyId='.$agencyId);
        $payload = $this->decodeJsonResponse();
        $members = $this->membersFromCollection($payload);

        $ids = array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $members,
        );

        self::assertContains(
            $online['offer']->getId(),
            $ids,
            (string) $this->client->getResponse()->getContent(),
        );

        $this->publicGet('/api/public/agency/offers?agencyId='.$offline['agency']->getId());
        $offlineMembers = $this->membersFromCollection($this->decodeJsonResponse());
        $offlineIds = array_map(static fn (array $row): string => (string) ($row['id'] ?? ''), $offlineMembers);
        self::assertNotContains($offline['offer']->getId(), $offlineIds);
    }

    public function testFilterOffersByOriginAndDestination(): void
    {
        $ws = $this->createPartnerWorkspace('FilterCatalog');
        $offer = $ws['offer'];
        $offer->setOrigin('UniqueOrigin'.$this->suffix);
        $offer->setDestination('UniqueDest'.$this->suffix);
        $offer->setOnlineSales(true);
        $this->em->flush();

        $this->publicGet('/api/public/agency/offers?origin=UniqueOrigin'.$this->suffix.'&destination=UniqueDest'.$this->suffix);
        $members = $this->membersFromCollection($this->decodeJsonResponse());
        self::assertCount(1, $members);
        self::assertSame($offer->getId(), $members[0]['id'] ?? null);
    }

    public function testGetOfferDetail(): void
    {
        $ws = $this->createPartnerWorkspace('OfferDetail');
        $this->enableOnlineSales($ws['offer']);

        $this->publicGet('/api/public/agency/offers/'.$ws['offer']->getId());
        $body = $this->decodeJsonResponse();

        self::assertSame($ws['offer']->getId(), $body['id'] ?? null);
        self::assertSame('Kinshasa', $body['origin'] ?? null);
        self::assertSame($ws['agency']->getName(), $body['agencyName'] ?? null);
        self::assertSame('BUS', $body['transportKind'] ?? null);
    }

    public function testGetOfferDetail404WhenNotOnline(): void
    {
        $ws = $this->createPartnerWorkspace('NotOnline');

        $this->publicGet('/api/public/agency/offers/'.$ws['offer']->getId(), 404);
    }

    public function testQuoteWithoutPass(): void
    {
        $ws = $this->createPartnerWorkspace('QuoteNoPass');
        $this->enableOnlineSales($ws['offer']);

        $this->publicGet('/api/public/agency/offers/'.$ws['offer']->getId().'/quote');
        $body = $this->decodeJsonResponse();

        self::assertSame(85000, $body['ticketPrice'] ?? null);
        self::assertGreaterThan(0, $body['passPrice'] ?? 0);
        self::assertSame(
            ($body['ticketPrice'] ?? 0) + ($body['passPrice'] ?? 0),
            $body['total'] ?? null,
        );
        self::assertFalse($body['hasExistingPass'] ?? true);
    }

    public function testQuoteWithValidPass(): void
    {
        $ws = $this->createPartnerWorkspace('QuotePass');
        $this->enableOnlineSales($ws['offer']);
        $this->seedIssuedPass('OP-PUBLIC-QUOTE');

        $this->publicGet('/api/public/agency/offers/'.$ws['offer']->getId().'/quote?okapiPassRef=OP-PUBLIC-QUOTE');
        $body = $this->decodeJsonResponse();

        self::assertSame(0, $body['passPrice'] ?? -1);
        self::assertTrue($body['hasExistingPass'] ?? false);
        self::assertSame(85000, $body['total'] ?? null);
    }

    public function testSeatsRequiresTravelDate(): void
    {
        $ws = $this->createPartnerWorkspace('SeatsNoDate');
        $this->enableOnlineSales($ws['offer']);

        $this->publicGet('/api/public/agency/offers/'.$ws['offer']->getId().'/seats', 422);
    }

    public function testSeatsReturnsLayout(): void
    {
        $ws = $this->createPartnerWorkspace('SeatsLayout');
        $this->enableOnlineSales($ws['offer']);
        $travelDate = $this->travelDate('+5 days');

        $this->publicGet('/api/public/agency/offers/'.$ws['offer']->getId().'/seats?travelDate='.$travelDate);
        $body = $this->decodeJsonResponse();

        self::assertSame($travelDate, $body['travelDate'] ?? null);
        self::assertSame(8, $body['capacity'] ?? null);
        self::assertSame(8, $body['availableCount'] ?? null);
        self::assertFalse($body['isFull'] ?? true);
        self::assertSame('BUS', $body['layout']['kind'] ?? null);
        self::assertIsArray($body['occupiedSeats'] ?? null);
    }

    private function enableOnlineSales(AgencyOffer $offer): void
    {
        $managed = $this->em->find(AgencyOffer::class, $offer->getId());
        self::assertInstanceOf(AgencyOffer::class, $managed);
        $managed->setOnlineSales(true);
        $this->em->flush();
        $this->em->refresh($managed);
        self::assertTrue($managed->isOnlineSales());
    }

    private function publicGet(string $uri, int $expectedStatus = 200): void
    {
        $this->client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(
            $expectedStatus,
            $this->client->getResponse()->getStatusCode(),
            sprintf('GET %s: %s', $uri, $this->client->getResponse()->getContent()),
        );
    }

    /** @return array<string, mixed> */
    private function decodeJsonResponse(): array
    {
        $decoded = json_decode($this->client->getResponse()->getContent() ?: '{}', true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private function membersFromCollection(array $payload): array
    {
        if (isset($payload['member']) && \is_array($payload['member'])) {
            return $payload['member'];
        }
        if (isset($payload['hydra:member']) && \is_array($payload['hydra:member'])) {
            return $payload['hydra:member'];
        }
        // Plain JSON array (no Hydra wrapper)
        if (array_is_list($payload)) {
            return $payload;
        }

        return [];
    }
}
