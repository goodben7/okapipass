<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyDriverDocumentDto;
use App\Dto\Agency\UpdateAgencyDriverDocumentDto;
use App\Entity\AgencyDriver;
use App\Entity\AgencyDriverDocument;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyDriverDocumentRepository;
use App\Repository\AgencyDriverRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyDriverDocumentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyDriverRepository $drivers,
        private AgencyDriverDocumentRepository $documents,
    ) {
    }

    public function create(CreateAgencyDriverDocumentDto $dto): AgencyDriverDocument
    {
        $this->agencyContext->requirePermission(AgencyPermission::DRIVER_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $type = strtoupper(trim((string) $dto->type));
        if (!\in_array($type, AgencyDriverDocument::getTypesAsList(), true)) {
            throw new UnprocessableEntityException('Invalid document type.');
        }

        $doc = new AgencyDriverDocument();
        $doc->setAgency($agency);
        $doc->setDriver($this->resolveDriver((string) $dto->driver, $agency->getId()));
        $doc->setType($type);
        $doc->setLabel((string) $dto->label);
        $doc->setIssuedAt(null !== $dto->issuedAt ? new \DateTimeImmutable($dto->issuedAt) : null);
        $doc->setExpiresAt(null !== $dto->expiresAt ? new \DateTimeImmutable($dto->expiresAt) : null);
        $doc->setFileUrl($dto->fileUrl);
        $doc->setNotes($dto->notes);

        $this->em->persist($doc);
        $this->em->flush();

        return $doc;
    }

    public function update(AgencyDriverDocument $doc, UpdateAgencyDriverDocumentDto $dto): AgencyDriverDocument
    {
        $this->agencyContext->requirePermission(AgencyPermission::DRIVER_WRITE);
        $this->agencyContext->assertOwns($doc->getAgency());

        if (null !== $dto->type) {
            $type = strtoupper(trim($dto->type));
            if (!\in_array($type, AgencyDriverDocument::getTypesAsList(), true)) {
                throw new UnprocessableEntityException('Invalid document type.');
            }
            $doc->setType($type);
        }
        if (null !== $dto->label) {
            $doc->setLabel($dto->label);
        }
        if (null !== $dto->issuedAt) {
            $doc->setIssuedAt('' === $dto->issuedAt ? null : new \DateTimeImmutable($dto->issuedAt));
        }
        if (null !== $dto->expiresAt) {
            $doc->setExpiresAt('' === $dto->expiresAt ? null : new \DateTimeImmutable($dto->expiresAt));
        }
        if (null !== $dto->fileUrl) {
            $doc->setFileUrl('' === $dto->fileUrl ? null : $dto->fileUrl);
        }
        if (null !== $dto->notes) {
            $doc->setNotes($dto->notes);
        }

        $this->em->flush();

        return $doc;
    }

    public function delete(AgencyDriverDocument $doc): void
    {
        $this->agencyContext->requirePermission(AgencyPermission::DRIVER_WRITE);
        $this->agencyContext->assertOwns($doc->getAgency());
        $this->em->remove($doc);
        $this->em->flush();
    }

    private function resolveDriver(string $ref, ?string $agencyId): AgencyDriver
    {
        $id = $this->extractId($ref);
        $driver = $this->drivers->find($id);
        if (!$driver instanceof AgencyDriver || $driver->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException('Driver not found.');
        }

        return $driver;
    }

    private function extractId(string $ref): string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));
            $ref = (string) end($parts);
        }

        return $ref;
    }
}
