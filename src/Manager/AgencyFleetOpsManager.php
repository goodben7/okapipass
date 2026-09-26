<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyDepartureChecklistDto;
use App\Dto\Agency\CreateAgencyFuelLogDto;
use App\Entity\Agency;
use App\Entity\AgencyDepartureChecklist;
use App\Entity\AgencyDriver;
use App\Entity\AgencyFuelLog;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use App\Exception\UnauthorizedActionException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyDriverRepository;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyFleetOpsManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyTransportRepository $transports,
        private AgencyDriverRepository $drivers,
        private AgencyOfferRepository $offers,
        private AgencyEmbarkationRepository $embarkations,
    ) {
    }

    public function createFuelLog(CreateAgencyFuelLogDto $dto): AgencyFuelLog
    {
        $this->agencyContext->requirePermission(AgencyPermission::FLEET_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $transport = $this->resolveTransport((string) $dto->transport, $agency);

        $log = new AgencyFuelLog();
        $log->setAgency($agency);
        $log->setTransport($transport);
        $log->setDriver($this->resolveDriver($dto->driver, $agency));
        $log->setLiters((int) $dto->liters);
        $log->setAmount((int) $dto->amount);
        $log->setCurrency(strtoupper((string) ($dto->currency ?? $agency->getDefaultCurrency())));
        $log->setOdometerKm($dto->odometerKm);
        $log->setNotes($dto->notes);

        if (null !== $dto->fueledAt && '' !== trim($dto->fueledAt)) {
            $fueledAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dto->fueledAt)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dto->fueledAt)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $dto->fueledAt);
            if (false === $fueledAt) {
                throw new UnprocessableEntityException('Invalid fueledAt datetime.');
            }
            $log->setFueledAt($fueledAt);
        }

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    public function createDepartureChecklist(CreateAgencyDepartureChecklistDto $dto): AgencyDepartureChecklist
    {
        $this->requireDriverOrFleetWrite();
        $agency = $this->agencyContext->requireAgency();
        $transport = $this->resolveTransport((string) $dto->transport, $agency);

        $travelDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $dto->travelDate);
        if (false === $travelDate) {
            throw new UnprocessableEntityException('Invalid travelDate.');
        }

        $checklist = new AgencyDepartureChecklist();
        $checklist->setAgency($agency);
        $checklist->setTransport($transport);
        $checklist->setDriver($this->resolveDriver($dto->driver, $agency));
        $checklist->setOffer($this->resolveOffer($dto->offer, $agency));
        $checklist->setTravelDate($travelDate);
        $checklist->setOdometerKm((int) $dto->odometerKm);
        $checklist->setFuelLevelPercent((int) $dto->fuelLevelPercent);
        $checklist->setVehicleOk($dto->vehicleOk ?? true);
        $checklist->setNotes($dto->notes);
        $checklist->setStatus(AgencyDepartureChecklist::STATUS_DRAFT);
        $checklist->setCreatedBy($this->agencyContext->getUser());

        $this->em->persist($checklist);
        $this->em->flush();

        return $checklist;
    }

    public function submitDepartureChecklist(AgencyDepartureChecklist $checklist): AgencyDepartureChecklist
    {
        $this->requireDriverOrFleetWrite();
        $this->agencyContext->assertOwns($checklist->getAgency());

        if (AgencyDepartureChecklist::STATUS_SUBMITTED === $checklist->getStatus()) {
            throw new UnprocessableEntityException('Checklist is already submitted.');
        }

        $checklist->setStatus(AgencyDepartureChecklist::STATUS_SUBMITTED);
        $checklist->setSubmittedAt(new \DateTimeImmutable('now'));

        $offer = $checklist->getOffer();
        $travelDate = $checklist->getTravelDate();
        if (null !== $offer && $travelDate instanceof \DateTimeImmutable) {
            $embarkation = $this->embarkations->findOneForOfferOnDate($offer, $travelDate);
            if ($embarkation instanceof \App\Entity\AgencyEmbarkation && null === $embarkation->getDepartedAt()) {
                $embarkation->setDepartedAt($checklist->getSubmittedAt());
                if (\App\Entity\AgencyEmbarkation::STATUS_DEPARTED !== $embarkation->getStatus()
                    && \App\Entity\AgencyEmbarkation::STATUS_DECLARED !== $embarkation->getStatus()
                    && \App\Entity\AgencyEmbarkation::STATUS_CLOSED !== $embarkation->getStatus()
                ) {
                    $embarkation->setStatus(\App\Entity\AgencyEmbarkation::STATUS_DEPARTED);
                }
            }
        }

        $this->em->flush();

        return $checklist;
    }

    private function requireDriverOrFleetWrite(): void
    {
        try {
            $this->agencyContext->requirePermission(AgencyPermission::DRIVER_WRITE);

            return;
        } catch (UnauthorizedActionException) {
        }

        $this->agencyContext->requirePermission(AgencyPermission::FLEET_WRITE);
    }

    private function resolveTransport(string $ref, Agency $agency): AgencyTransport
    {
        $transport = $this->transports->find($this->extractId($ref));
        if (!$transport instanceof AgencyTransport || $transport->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Transport not found.');
        }

        return $transport;
    }

    private function resolveDriver(?string $ref, Agency $agency): ?AgencyDriver
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $driver = $this->drivers->find($this->extractId($ref));
        if (!$driver instanceof AgencyDriver || $driver->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Driver not found.');
        }

        return $driver;
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
