<?php

namespace App\Manager;

use App\Dto\Agency\CreateAgencyOfferDto;
use App\Dto\Agency\UpdateAgencyOfferDto;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyOfferRepository;
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

        $this->em->persist($offer);
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
            $offer->setDepartureTime($dto->departureTime);
        }
        if (null !== $dto->durationMinutes) {
            $offer->setDurationMinutes($dto->durationMinutes);
        }
        if (null !== $dto->active) {
            $offer->setActive($dto->active);
        }

        $this->em->flush();

        return $offer;
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
