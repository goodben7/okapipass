<?php

namespace App\Manager;

use App\Dto\Agency\CreateAgencyDriverDto;
use App\Dto\Agency\UpdateAgencyDriverDto;
use App\Entity\AgencyDriver;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyDriverRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyDriverManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyDriverRepository $drivers,
    ) {
    }

    public function create(CreateAgencyDriverDto $dto): AgencyDriver
    {
        $agency = $this->agencyContext->requireAgency();
        $license = strtoupper(trim((string) $dto->licenseNumber));

        if (null !== $this->drivers->findOneByAgencyAndLicense($agency, $license)) {
            throw new ConflictException(sprintf('License number "%s" already exists for this agency.', $license));
        }

        $driver = new AgencyDriver();
        $driver->setAgency($agency);
        $driver->setFullName((string) $dto->fullName);
        $driver->setPhone((string) $dto->phone);
        $driver->setLicenseNumber($license);
        $driver->setLicenseExpiresAt($this->parseOptionalDate($dto->licenseExpiresAt));
        $driver->setStatus($dto->status ?? AgencyDriver::STATUS_ACTIVE);
        $driver->setNotes($dto->notes);

        $this->em->persist($driver);
        $this->em->flush();

        return $driver;
    }

    public function update(AgencyDriver $driver, UpdateAgencyDriverDto $dto): AgencyDriver
    {
        $this->agencyContext->assertOwns($driver->getAgency());

        if (null !== $dto->fullName) {
            $driver->setFullName($dto->fullName);
        }
        if (null !== $dto->phone) {
            $driver->setPhone($dto->phone);
        }
        if (null !== $dto->licenseNumber) {
            $license = strtoupper(trim($dto->licenseNumber));
            $existing = $this->drivers->findOneByAgencyAndLicense($driver->getAgency(), $license);
            if (null !== $existing && $existing->getId() !== $driver->getId()) {
                throw new ConflictException(sprintf('License number "%s" already exists for this agency.', $license));
            }
            $driver->setLicenseNumber($license);
        }
        if (null !== $dto->licenseExpiresAt) {
            $driver->setLicenseExpiresAt($this->parseOptionalDate($dto->licenseExpiresAt));
        }
        if (null !== $dto->status) {
            $driver->setStatus($dto->status);
        }
        if (null !== $dto->notes) {
            $driver->setNotes($dto->notes);
        }

        $this->em->flush();

        return $driver;
    }

    public function delete(AgencyDriver $driver): void
    {
        $this->agencyContext->assertOwns($driver->getAgency());

        if ($this->drivers->countEmbarkations($driver) > 0) {
            throw new ConflictException('Cannot delete driver while embarkations still reference them.');
        }

        $this->em->remove($driver);
        $this->em->flush();
    }

    public function getOwned(string $id): AgencyDriver
    {
        $driver = $this->drivers->find($id);
        if (!$driver instanceof AgencyDriver) {
            throw new UnavailableDataException('Driver not found.');
        }

        $this->agencyContext->assertOwns($driver->getAgency());

        return $driver;
    }

    public function resolveForAssignment(?string $ref, ?string $agencyId): ?AgencyDriver
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $driver = $this->getOwned($this->extractId($ref));
        if ($driver->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException('Driver not found.');
        }

        if (!$driver->isAssignable()) {
            throw new UnprocessableEntityException('Driver is not active — cannot assign to embarkation.');
        }

        return $driver;
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

    private function parseOptionalDate(?string $date): ?\DateTimeImmutable
    {
        if (null === $date || '' === trim($date)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $parsed) {
            throw new UnprocessableEntityException('Invalid licenseExpiresAt, expected YYYY-MM-DD.');
        }

        return $parsed->setTime(0, 0);
    }
}
