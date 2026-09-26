<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyFleetIncidentDto;
use App\Dto\Agency\UpdateAgencyFleetIncidentDto;
use App\Entity\Agency;
use App\Entity\AgencyDriver;
use App\Entity\AgencyFleetIncident;
use App\Entity\AgencyTransport;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyDriverRepository;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyFleetIncidentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyTransportRepository $transports,
        private AgencyDriverRepository $drivers,
    ) {
    }

    public function create(CreateAgencyFleetIncidentDto $dto): AgencyFleetIncident
    {
        $this->agencyContext->requirePermission(AgencyPermission::FLEET_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $type = strtoupper(trim((string) $dto->type));
        $severity = strtoupper(trim((string) $dto->severity));
        if (!\in_array($type, AgencyFleetIncident::getTypesAsList(), true)) {
            throw new UnprocessableEntityException('Invalid incident type.');
        }
        if (!\in_array($severity, AgencyFleetIncident::getSeveritiesAsList(), true)) {
            throw new UnprocessableEntityException('Invalid incident severity.');
        }

        $incident = new AgencyFleetIncident();
        $incident->setAgency($agency);
        $incident->setTransport($this->resolveTransport((string) $dto->transport, $agency));
        $incident->setDriver($this->resolveDriver($dto->driver, $agency));
        $incident->setType($type);
        $incident->setSeverity($severity);
        $incident->setLat($dto->lat);
        $incident->setLng($dto->lng);
        $incident->setPhotoUrl($dto->photoUrl);
        $incident->setNotes($dto->notes);
        $incident->setOccurredAt($this->parseDateTime($dto->occurredAt) ?? new \DateTimeImmutable());
        $incident->setStatus(AgencyFleetIncident::STATUS_OPEN);

        $this->em->persist($incident);
        $this->em->flush();

        return $incident;
    }

    public function update(AgencyFleetIncident $incident, UpdateAgencyFleetIncidentDto $dto): AgencyFleetIncident
    {
        $this->agencyContext->requirePermission(AgencyPermission::FLEET_WRITE);
        $this->agencyContext->assertOwns($incident->getAgency());

        if (AgencyFleetIncident::STATUS_OPEN !== $incident->getStatus()) {
            throw new UnprocessableEntityException('Only open incidents can be edited.');
        }

        if (null !== $dto->type) {
            $type = strtoupper(trim($dto->type));
            if (!\in_array($type, AgencyFleetIncident::getTypesAsList(), true)) {
                throw new UnprocessableEntityException('Invalid incident type.');
            }
            $incident->setType($type);
        }
        if (null !== $dto->severity) {
            $severity = strtoupper(trim($dto->severity));
            if (!\in_array($severity, AgencyFleetIncident::getSeveritiesAsList(), true)) {
                throw new UnprocessableEntityException('Invalid incident severity.');
            }
            $incident->setSeverity($severity);
        }
        if (null !== $dto->lat) {
            $incident->setLat($dto->lat);
        }
        if (null !== $dto->lng) {
            $incident->setLng($dto->lng);
        }
        if (null !== $dto->photoUrl) {
            $incident->setPhotoUrl($dto->photoUrl);
        }
        if (null !== $dto->notes) {
            $incident->setNotes($dto->notes);
        }
        if (null !== $dto->occurredAt) {
            $occurredAt = $this->parseDateTime($dto->occurredAt);
            if (!$occurredAt instanceof \DateTimeImmutable) {
                throw new UnprocessableEntityException('Invalid occurredAt datetime.');
            }
            $incident->setOccurredAt($occurredAt);
        }

        $this->em->flush();

        return $incident;
    }

    public function resolve(AgencyFleetIncident $incident): AgencyFleetIncident
    {
        $this->agencyContext->requirePermission(AgencyPermission::FLEET_WRITE);
        $this->agencyContext->assertOwns($incident->getAgency());

        if (AgencyFleetIncident::STATUS_OPEN !== $incident->getStatus()) {
            throw new UnprocessableEntityException('Only open incidents can be resolved.');
        }

        $incident->setStatus(AgencyFleetIncident::STATUS_RESOLVED);
        $this->em->flush();

        return $incident;
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

    private function parseDateTime(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $value);

        return false === $parsed ? null : $parsed;
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
