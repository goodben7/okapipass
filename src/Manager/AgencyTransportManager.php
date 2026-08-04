<?php

namespace App\Manager;

use App\Domain\Agency\SeatLayoutBuilder;
use App\Dto\Agency\CreateAgencyTransportDto;
use App\Dto\Agency\UpdateAgencyTransportDto;
use App\Entity\AgencyTransport;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

class AgencyTransportManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyTransportRepository $transports,
        private AgencyOfferRepository $offers,
        private SeatLayoutBuilder $seatLayoutBuilder,
    ) {
    }

    public function create(CreateAgencyTransportDto $dto): AgencyTransport
    {
        $agency = $this->agencyContext->requireAgency();
        $kind = $dto->kind;
        $capacity = (int) $dto->capacity;

        // Validates kind/capacity by building layout (spec §5.1).
        $this->seatLayoutBuilder->build($kind, $capacity);

        $plate = strtoupper(trim((string) $dto->plateNumber));
        if (null !== $this->transports->findOneByAgencyAndPlate($agency, $plate)) {
            throw new ConflictException(sprintf('Plate number "%s" already exists for this agency.', $plate));
        }

        $transport = new AgencyTransport();
        $transport->setAgency($agency);
        $transport->setLabel((string) $dto->label);
        $transport->setKind($kind);
        $transport->setPlateNumber($plate);
        $transport->setCapacity($capacity);
        $transport->setStatus($dto->status ?? AgencyTransport::STATUS_ACTIVE);

        $this->em->persist($transport);
        $this->em->flush();

        return $transport;
    }

    public function update(AgencyTransport $transport, UpdateAgencyTransportDto $dto): AgencyTransport
    {
        $this->agencyContext->assertOwns($transport->getAgency());

        if (null !== $dto->label) {
            $transport->setLabel($dto->label);
        }

        $kind = $dto->kind ?? $transport->getKind();
        $capacity = $dto->capacity ?? $transport->getCapacity();
        if (null !== $dto->kind || null !== $dto->capacity) {
            $this->seatLayoutBuilder->build((string) $kind, (int) $capacity);
            if (null !== $dto->kind) {
                $transport->setKind($dto->kind);
            }
            if (null !== $dto->capacity) {
                $transport->setCapacity($dto->capacity);
            }
        }

        if (null !== $dto->plateNumber) {
            $plate = strtoupper(trim($dto->plateNumber));
            $existing = $this->transports->findOneByAgencyAndPlate($transport->getAgency(), $plate);
            if (null !== $existing && $existing->getId() !== $transport->getId()) {
                throw new ConflictException(sprintf('Plate number "%s" already exists for this agency.', $plate));
            }
            $transport->setPlateNumber($plate);
        }

        if (null !== $dto->status) {
            $transport->setStatus($dto->status);
        }

        $this->em->flush();

        return $transport;
    }

    public function delete(AgencyTransport $transport): void
    {
        $this->agencyContext->assertOwns($transport->getAgency());

        $activeOffers = $this->offers->countActiveByTransport($transport);
        if ($activeOffers > 0) {
            throw new ConflictException('Cannot delete transport while active offers exist.');
        }

        // Also block if any offer remains (inactive) — soft dependency.
        if ($this->offers->countByTransport($transport) > 0) {
            throw new ConflictException('Cannot delete transport while offers still reference it.');
        }

        $this->em->remove($transport);
        $this->em->flush();
    }

    public function getOwned(string $id): AgencyTransport
    {
        $transport = $this->transports->find($id);
        if (null === $transport) {
            throw new UnavailableDataException('Transport not found.');
        }

        $this->agencyContext->assertOwns($transport->getAgency());

        return $transport;
    }
}
