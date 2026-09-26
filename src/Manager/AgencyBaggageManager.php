<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\RecordBaggageResult;
use App\Dto\Agency\ReleaseNoShowsDto;
use App\Entity\AgencyBaggageExcess;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyBaggageManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyTicketRepository $tickets,
        private AgencyOfferRepository $offers,
    ) {
    }

    public function recordBaggage(AgencyTicket $ticket, int $kg): RecordBaggageResult
    {
        $this->agencyContext->requirePermission(AgencyPermission::TICKET_WRITE);
        $this->agencyContext->assertOwns($ticket->getAgency());

        if ($ticket->isCancelled() || $ticket->isNoShow()) {
            throw new UnprocessableEntityException('Cannot record baggage on a cancelled or no-show ticket.');
        }

        $offer = $ticket->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('Ticket has no offer.');
        }

        $freeKg = min($kg, $offer->getBaggageFreeKg());
        $excessKg = max(0, $kg - $offer->getBaggageFreeKg());
        $unitPrice = $offer->getBaggageExcessPricePerKg();
        $amount = $excessKg * $unitPrice;

        $ticket->setBaggageKg($kg);

        $excess = new AgencyBaggageExcess();
        $excess->setAgency($ticket->getAgency());
        $excess->setTicket($ticket);
        $excess->setKg($kg);
        $excess->setFreeKgApplied($freeKg);
        $excess->setExcessKg($excessKg);
        $excess->setUnitPrice($unitPrice);
        $excess->setAmount($amount);
        $excess->setCurrency($ticket->getCurrency());
        $excess->setStatus(
            0 === $amount ? AgencyBaggageExcess::STATUS_WAIVED : AgencyBaggageExcess::STATUS_RECORDED
        );

        $this->em->persist($excess);
        $this->em->flush();

        return new RecordBaggageResult($excess, $amount, $excessKg);
    }

    /**
     * @return array{
     *     offerId: string,
     *     travelDate: string,
     *     tickets: list<array<string, mixed>>,
     *     boardedCount: int,
     *     issuedCount: int,
     *     noShowCount: int
     * }
     */
    public function buildManifest(string $offerId, string $travelDateRaw): array
    {
        $agency = $this->agencyContext->requireAgency();
        $offer = $this->offers->find($offerId);
        if (!$offer instanceof AgencyOffer) {
            throw new UnavailableDataException('Offer not found.');
        }
        $this->agencyContext->assertOwns($offer->getAgency());

        $travelDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $travelDateRaw);
        if (false === $travelDate) {
            throw new UnprocessableEntityException('Invalid travelDate.');
        }

        $tickets = $this->tickets->findForManifest($offer, $travelDate);
        $rows = [];
        $boardedCount = 0;
        $issuedCount = 0;
        $noShowCount = 0;

        foreach ($tickets as $ticket) {
            $status = $ticket->getStatus();
            if (AgencyTicket::STATUS_BOARDED === $status) {
                ++$boardedCount;
            } elseif (AgencyTicket::STATUS_ISSUED === $status) {
                ++$issuedCount;
            } elseif (AgencyTicket::STATUS_NO_SHOW === $status) {
                ++$noShowCount;
            }

            $rows[] = [
                'id' => $ticket->getId(),
                'reference' => $ticket->getReference(),
                'passengerName' => $ticket->getPassengerName(),
                'seatNumber' => $ticket->getSeatNumber(),
                'status' => $status,
                'baggageKg' => $ticket->getBaggageKg(),
            ];
        }

        return [
            'offerId' => (string) $offer->getId(),
            'travelDate' => $travelDate->format('Y-m-d'),
            'tickets' => $rows,
            'boardedCount' => $boardedCount,
            'issuedCount' => $issuedCount,
            'noShowCount' => $noShowCount,
        ];
    }

    public function releaseNoShows(ReleaseNoShowsDto $dto): int
    {
        $this->agencyContext->requirePermission(AgencyPermission::EMBARKATION_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $offer = $this->offers->find((string) $dto->offerId);
        if (!$offer instanceof AgencyOffer) {
            throw new UnavailableDataException('Offer not found.');
        }
        $this->agencyContext->assertOwns($offer->getAgency());

        $travelDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $dto->travelDate);
        if (false === $travelDate) {
            throw new UnprocessableEntityException('Invalid travelDate.');
        }

        $today = new \DateTimeImmutable('today');
        if ($travelDate > $today) {
            throw new UnprocessableEntityException('Cannot release no-shows for a future travel date.');
        }

        if ($travelDate->format('Y-m-d') === $today->format('Y-m-d')) {
            $this->assertReleaseWindowOpen($offer);
        }

        $released = 0;
        foreach ($this->tickets->findIssuedForOfferDate($offer, $travelDate) as $ticket) {
            // ISSUED tickets may already be listed on a planned embarkation; still free the seat.
            $ticket->setStatus(AgencyTicket::STATUS_NO_SHOW);
            ++$released;
        }

        $this->em->flush();

        return $released;
    }

    private function assertReleaseWindowOpen(AgencyOffer $offer): void
    {
        $departureTime = (string) $offer->getDepartureTime();
        if (!preg_match('/^(\d{2}):(\d{2})$/', $departureTime, $matches)) {
            throw new UnprocessableEntityException('Offer departure time is invalid.');
        }

        $today = new \DateTimeImmutable('today');
        $departure = $today->setTime((int) $matches[1], (int) $matches[2]);
        $releaseAt = $departure->modify(sprintf('-%d minutes', $offer->getNoShowReleaseMinutes()));

        if (new \DateTimeImmutable('now') < $releaseAt) {
            throw new UnprocessableEntityException('Too early to release no-shows for today\'s departure.');
        }
    }
}
