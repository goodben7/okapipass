<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyParcelDto;
use App\Dto\Agency\UpdateAgencyParcelDto;
use App\Entity\Agency;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyOffer;
use App\Entity\AgencyParcel;
use App\Entity\AgencyTransport;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyParcelRepository;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyParcelManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyOfferRepository $offers,
        private AgencyTransportRepository $transports,
        private AgencyEmbarkationRepository $embarkations,
        private AgencyParcelRepository $parcels,
    ) {
    }

    public function create(CreateAgencyParcelDto $dto): AgencyParcel
    {
        $this->agencyContext->requirePermission(AgencyPermission::BOOKING_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $parcel = new AgencyParcel();
        $parcel->setAgency($agency);
        $parcel->setOffer($this->resolveOffer($dto->offer, $agency));
        $parcel->setTransport($this->resolveTransport($dto->transport, $agency));
        $parcel->setEmbarkation($this->resolveEmbarkation($dto->embarkation, $agency));
        $parcel->setSenderName((string) $dto->senderName);
        $parcel->setSenderPhone((string) $dto->senderPhone);
        $parcel->setRecipientName((string) $dto->recipientName);
        $parcel->setRecipientPhone((string) $dto->recipientPhone);
        $parcel->setWeightKg($dto->weightKg);
        $parcel->setFee((int) $dto->fee);
        $parcel->setCurrency(strtoupper((string) ($dto->currency ?? $agency->getDefaultCurrency())));
        $parcel->setTravelDate($this->parseDate($dto->travelDate));
        $parcel->setNotes($dto->notes);
        $parcel->setStatus(AgencyParcel::STATUS_BOOKED);
        $parcel->setTrackingCode($this->generateTrackingCode($agency));

        $this->em->persist($parcel);
        $this->em->flush();

        return $parcel;
    }

    public function update(AgencyParcel $parcel, UpdateAgencyParcelDto $dto): AgencyParcel
    {
        $this->agencyContext->requirePermission(AgencyPermission::BOOKING_WRITE);
        $this->agencyContext->assertOwns($parcel->getAgency());
        $this->assertMutable($parcel);

        if (null !== $dto->offer) {
            $parcel->setOffer($this->resolveOffer($dto->offer, $parcel->getAgency()));
        }
        if (null !== $dto->transport) {
            $parcel->setTransport($this->resolveTransport($dto->transport, $parcel->getAgency()));
        }
        if (null !== $dto->embarkation) {
            $parcel->setEmbarkation($this->resolveEmbarkation($dto->embarkation, $parcel->getAgency()));
        }
        if (null !== $dto->senderName) {
            $parcel->setSenderName($dto->senderName);
        }
        if (null !== $dto->senderPhone) {
            $parcel->setSenderPhone($dto->senderPhone);
        }
        if (null !== $dto->recipientName) {
            $parcel->setRecipientName($dto->recipientName);
        }
        if (null !== $dto->recipientPhone) {
            $parcel->setRecipientPhone($dto->recipientPhone);
        }
        if (null !== $dto->weightKg) {
            $parcel->setWeightKg($dto->weightKg);
        }
        if (null !== $dto->fee) {
            $parcel->setFee($dto->fee);
        }
        if (null !== $dto->currency) {
            $parcel->setCurrency(strtoupper($dto->currency));
        }
        if (null !== $dto->travelDate) {
            $parcel->setTravelDate($this->parseDate($dto->travelDate));
        }
        if (null !== $dto->notes) {
            $parcel->setNotes($dto->notes);
        }

        $this->em->flush();

        return $parcel;
    }

    public function deliver(AgencyParcel $parcel): AgencyParcel
    {
        $this->agencyContext->requirePermission(AgencyPermission::BOOKING_WRITE);
        $this->agencyContext->assertOwns($parcel->getAgency());

        if (!\in_array($parcel->getStatus(), [AgencyParcel::STATUS_BOOKED, AgencyParcel::STATUS_IN_TRANSIT], true)) {
            throw new UnprocessableEntityException('Only booked or in-transit parcels can be delivered.');
        }

        $parcel->setStatus(AgencyParcel::STATUS_DELIVERED);
        $this->em->flush();

        return $parcel;
    }

    public function cancel(AgencyParcel $parcel): AgencyParcel
    {
        $this->agencyContext->requirePermission(AgencyPermission::BOOKING_WRITE);
        $this->agencyContext->assertOwns($parcel->getAgency());

        if (AgencyParcel::STATUS_DELIVERED === $parcel->getStatus()) {
            throw new UnprocessableEntityException('Delivered parcels cannot be cancelled.');
        }
        if (AgencyParcel::STATUS_CANCELLED === $parcel->getStatus()) {
            throw new ConflictException('Parcel is already cancelled.');
        }

        $parcel->setStatus(AgencyParcel::STATUS_CANCELLED);
        $this->em->flush();

        return $parcel;
    }

    public function markInTransit(AgencyParcel $parcel): AgencyParcel
    {
        if (AgencyParcel::STATUS_BOOKED !== $parcel->getStatus()) {
            throw new UnprocessableEntityException('Only booked parcels can be marked in transit.');
        }

        $parcel->setStatus(AgencyParcel::STATUS_IN_TRANSIT);
        $this->em->flush();

        return $parcel;
    }

    private function generateTrackingCode(Agency $agency): string
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $code = sprintf('PL-%s', strtoupper(substr(bin2hex(random_bytes(3)), 0, 4)));
            if (!$this->parcels->existsTrackingCode($agency, $code)) {
                return $code;
            }
        }

        throw new ConflictException('Unable to generate unique parcel tracking code.');
    }

    private function assertMutable(AgencyParcel $parcel): void
    {
        if (\in_array($parcel->getStatus(), [AgencyParcel::STATUS_DELIVERED, AgencyParcel::STATUS_CANCELLED], true)) {
            throw new UnprocessableEntityException('Delivered or cancelled parcels cannot be edited.');
        }
    }

    private function resolveOffer(?string $ref, Agency $agency): ?AgencyOffer
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $offer = $this->offers->find($this->extractId($ref));
        if (!$offer instanceof AgencyOffer || $offer->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Offer not found.');
        }

        return $offer;
    }

    private function resolveTransport(?string $ref, Agency $agency): ?AgencyTransport
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $transport = $this->transports->find($this->extractId($ref));
        if (!$transport instanceof AgencyTransport || $transport->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Transport not found.');
        }

        return $transport;
    }

    private function resolveEmbarkation(?string $ref, Agency $agency): ?AgencyEmbarkation
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $embarkation = $this->embarkations->find($this->extractId($ref));
        if (!$embarkation instanceof AgencyEmbarkation || $embarkation->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Embarkation not found.');
        }

        return $embarkation;
    }

    private function parseDate(?string $date): ?\DateTimeImmutable
    {
        if (null === $date || '' === trim($date)) {
            return null;
        }

        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $d) {
            throw new UnprocessableEntityException('Invalid travelDate, expected YYYY-MM-DD.');
        }

        return $d->setTime(0, 0);
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
