<?php

namespace App\Manager;

use App\Dto\Agency\CreateAgencyOfferDto;
use App\Dto\Agency\UpdateAgencyOfferDto;
use App\Entity\AgencyOffer;
use App\Entity\AgencyOfferPriceHistory;
use App\Entity\AgencyOfferScheduleHistory;
use App\Entity\AgencyTransport;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyOfferPriceHistoryRepository;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyOfferScheduleHistoryRepository;
use App\Repository\AgencyTicketRepository;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

class AgencyOfferManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyOfferRepository $offers,
        private AgencyTransportRepository $transports,
        private AgencyBookingRepository $bookings,
        private AgencyTicketRepository $tickets,
        private AgencyOfferPriceHistoryRepository $priceHistory,
        private AgencyOfferScheduleHistoryRepository $scheduleHistory,
    ) {
    }

    public function create(CreateAgencyOfferDto $dto): AgencyOffer
    {
        $agency = $this->agencyContext->requireAgency();
        $transport = $this->resolveTransport((string) $dto->transport, $agency->getId());

        if (!$transport->isActiveForSale()) {
            throw new UnprocessableEntityException('Transport is not ACTIVE — sales are blocked (MAINTENANCE/INACTIVE).');
        }

        $currency = strtoupper((string) ($dto->currency ?? $agency->getDefaultCurrency()));
        if (!$agency->supportsCurrency($currency)) {
            throw new UnprocessableEntityException(sprintf(
                'Currency %s is not supported by this agency.',
                $currency
            ));
        }

        $offer = new AgencyOffer();
        $offer->setAgency($agency);
        $offer->setTransport($transport);
        $offer->setLabel((string) $dto->label);
        $offer->setOrigin((string) $dto->origin);
        $offer->setDestination((string) $dto->destination);
        $offer->setTicketPrice((int) $dto->ticketPrice);
        $offer->setCurrency($currency);
        $offer->setDepartureTime((string) $dto->departureTime);
        $offer->setDurationMinutes((int) $dto->durationMinutes);
        $offer->setActive($dto->active ?? true);
        $offer->setOnlineSales($dto->onlineSales ?? false);
        $offer->setBookingHoldMinutes($dto->bookingHoldMinutes ?? AgencyOffer::DEFAULT_BOOKING_HOLD_MINUTES);
        $offer->setBaggageFreeKg($dto->baggageFreeKg ?? AgencyOffer::DEFAULT_BAGGAGE_FREE_KG);
        $offer->setBaggageExcessPricePerKg($dto->baggageExcessPricePerKg ?? 0);
        $offer->setNoShowReleaseMinutes($dto->noShowReleaseMinutes ?? AgencyOffer::DEFAULT_NOSHOW_RELEASE_MINUTES);
        if (null !== $dto->minUnaccompaniedAge) {
            $offer->setMinUnaccompaniedAge($dto->minUnaccompaniedAge);
        }

        $this->em->persist($offer);
        $this->em->flush();

        $history = new AgencyOfferPriceHistory();
        $history->setOffer($offer);
        $history->setTicketPrice((int) $offer->getTicketPrice());
        $history->setEffectiveFrom(new \DateTimeImmutable('now'));
        $this->em->persist($history);
        $this->em->flush();

        return $offer;
    }

    public function update(AgencyOffer $offer, UpdateAgencyOfferDto $dto): AgencyOffer
    {
        $this->agencyContext->assertOwns($offer->getAgency());

        if (null !== $dto->transport) {
            $transport = $this->resolveTransport($dto->transport, $offer->getAgency()->getId());
            $offer->setTransport($transport);
        }

        if (null !== $dto->label) {
            $offer->setLabel($dto->label);
        }
        if (null !== $dto->origin) {
            $offer->setOrigin($dto->origin);
        }
        if (null !== $dto->destination) {
            $offer->setDestination($dto->destination);
        }
        if (null !== $dto->ticketPrice) {
            $this->appendPriceHistory($offer, (int) $dto->ticketPrice);
            $offer->setTicketPrice($dto->ticketPrice);
        }
        if (null !== $dto->currency) {
            $currency = strtoupper($dto->currency);
            if (!$offer->getAgency()?->supportsCurrency($currency)) {
                throw new UnprocessableEntityException(sprintf(
                    'Currency %s is not supported by this agency.',
                    $currency
                ));
            }
            $offer->setCurrency($currency);
        }
        if (null !== $dto->departureTime) {
            $this->appendScheduleHistory($offer, (string) $dto->departureTime);
            $offer->setDepartureTime($dto->departureTime);
        }
        if (null !== $dto->durationMinutes) {
            $offer->setDurationMinutes($dto->durationMinutes);
        }
        if (null !== $dto->active) {
            $offer->setActive($dto->active);
        }
        if (null !== $dto->onlineSales) {
            $offer->setOnlineSales($dto->onlineSales);
        }
        if (null !== $dto->bookingHoldMinutes) {
            $offer->setBookingHoldMinutes($dto->bookingHoldMinutes);
        }
        if (null !== $dto->baggageFreeKg) {
            $offer->setBaggageFreeKg($dto->baggageFreeKg);
        }
        if (null !== $dto->baggageExcessPricePerKg) {
            $offer->setBaggageExcessPricePerKg($dto->baggageExcessPricePerKg);
        }
        if (null !== $dto->noShowReleaseMinutes) {
            $offer->setNoShowReleaseMinutes($dto->noShowReleaseMinutes);
        }
        if (null !== $dto->minUnaccompaniedAge) {
            $offer->setMinUnaccompaniedAge($dto->minUnaccompaniedAge);
        }

        $this->em->flush();

        return $offer;
    }

    private function appendPriceHistory(AgencyOffer $offer, int $newPrice): void
    {
        if ((int) $offer->getTicketPrice() === $newPrice) {
            return;
        }

        $now = new \DateTimeImmutable('now');
        $open = $this->priceHistory->findOpenForOffer($offer);
        if ($open instanceof AgencyOfferPriceHistory) {
            $open->setEffectiveTo($now);
        } else {
            $bootstrap = new AgencyOfferPriceHistory();
            $bootstrap->setOffer($offer);
            $bootstrap->setTicketPrice((int) $offer->getTicketPrice());
            $bootstrap->setEffectiveFrom($offer->getCreatedAt() ?? $now);
            $bootstrap->setEffectiveTo($now);
            $this->em->persist($bootstrap);
        }

        $row = new AgencyOfferPriceHistory();
        $row->setOffer($offer);
        $row->setTicketPrice($newPrice);
        $row->setEffectiveFrom($now);
        $this->em->persist($row);
    }

    private function appendScheduleHistory(AgencyOffer $offer, string $newTime): void
    {
        if ((string) $offer->getDepartureTime() === $newTime) {
            return;
        }

        $now = new \DateTimeImmutable('now');
        $open = $this->scheduleHistory->findOpenForOffer($offer);
        if ($open instanceof AgencyOfferScheduleHistory) {
            $open->setEffectiveTo($now);
        } else {
            $bootstrap = new AgencyOfferScheduleHistory();
            $bootstrap->setOffer($offer);
            $bootstrap->setDepartureTime((string) ($offer->getDepartureTime() ?? '00:00'));
            $bootstrap->setEffectiveFrom($offer->getCreatedAt() ?? $now);
            $bootstrap->setEffectiveTo($now);
            $this->em->persist($bootstrap);
        }

        $row = new AgencyOfferScheduleHistory();
        $row->setOffer($offer);
        $row->setDepartureTime($newTime);
        $row->setEffectiveFrom($now);
        $this->em->persist($row);
    }

    /**
     * @return list<AgencyOfferScheduleHistory>
     */
    public function listScheduleHistory(AgencyOffer $offer): array
    {
        $this->agencyContext->assertOwns($offer->getAgency());

        return $this->scheduleHistory->findForOffer($offer);
    }

    public function delete(AgencyOffer $offer): void
    {
        $this->agencyContext->assertOwns($offer->getAgency());

        $from = new \DateTimeImmutable('today');
        if ($this->bookings->countFutureByOffer($offer, $from) > 0
            || $this->tickets->countFutureByOffer($offer, $from) > 0
        ) {
            throw new ConflictException('Cannot delete offer while future bookings or tickets exist.');
        }

        $this->em->remove($offer);
        $this->em->flush();
    }

    private function resolveTransport(string $transportRef, ?string $agencyId): AgencyTransport
    {
        $id = $this->extractId($transportRef);
        $transport = $this->transports->find($id);

        if (null === $transport) {
            throw new UnavailableDataException(sprintf('Transport "%s" not found.', $id));
        }

        if ($transport->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('Transport "%s" not found.', $id));
        }

        return $transport;
    }

    private function extractId(string $ref): string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));

            return (string) end($parts);
        }

        return $ref;
    }
}
