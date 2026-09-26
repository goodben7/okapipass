<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyBlacklistDto;
use App\Dto\Agency\UpdateAgencyBlacklistDto;
use App\Entity\Agency;
use App\Entity\AgencyBlacklistEntry;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBlacklistEntryRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyBlacklistManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyBlacklistEntryRepository $entries,
    ) {
    }

    public function create(CreateAgencyBlacklistDto $dto): AgencyBlacklistEntry
    {
        $this->agencyContext->requirePermission(AgencyPermission::STAFF_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $entry = new AgencyBlacklistEntry();
        $entry->setAgency($agency);
        $entry->setType((string) $dto->type);
        $entry->setValue((string) $dto->value);
        $entry->setReason($dto->reason);
        $entry->setActive(false !== $dto->active);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    public function update(AgencyBlacklistEntry $entry, UpdateAgencyBlacklistDto $dto): AgencyBlacklistEntry
    {
        $this->agencyContext->requirePermission(AgencyPermission::STAFF_WRITE);
        $this->agencyContext->assertOwns($entry->getAgency());

        if (null !== $dto->type) {
            $entry->setType($dto->type);
        }
        if (null !== $dto->value) {
            $entry->setValue($dto->value);
        }
        if (null !== $dto->reason) {
            $entry->setReason($dto->reason);
        }
        if (null !== $dto->active) {
            $entry->setActive($dto->active);
        }

        $this->em->flush();

        return $entry;
    }

    public function delete(AgencyBlacklistEntry $entry): void
    {
        $this->agencyContext->requirePermission(AgencyPermission::STAFF_WRITE);
        $this->agencyContext->assertOwns($entry->getAgency());
        $this->em->remove($entry);
        $this->em->flush();
    }

    public function assertNotBlacklisted(Agency $agency, ?string $phone, ?string $passengerId): void
    {
        if (null !== $phone && '' !== trim($phone)) {
            $match = $this->entries->findActiveMatch($agency, AgencyBlacklistEntry::TYPE_PHONE, $phone);
            if ($match instanceof AgencyBlacklistEntry) {
                throw new UnprocessableEntityException('Passenger phone is blacklisted for this agency.');
            }
        }
        if (null !== $passengerId && '' !== trim($passengerId)) {
            $match = $this->entries->findActiveMatch($agency, AgencyBlacklistEntry::TYPE_ID_DOCUMENT, $passengerId);
            if ($match instanceof AgencyBlacklistEntry) {
                throw new UnprocessableEntityException('Passenger ID document is blacklisted for this agency.');
            }
        }
    }
}
