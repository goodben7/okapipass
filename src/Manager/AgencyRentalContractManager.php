<?php

namespace App\Manager;

use App\Dto\Agency\CreateAgencyRentalContractDto;
use App\Dto\Agency\UpdateAgencyRentalContractDto;
use App\Entity\AgencyRentalContract;
use App\Entity\AgencyTransport;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyRentalContractRepository;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyRentalContractManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyRentalContractRepository $contracts,
        private AgencyTransportRepository $transports,
        private AgencyDriverManager $drivers,
    ) {
    }

    public function create(CreateAgencyRentalContractDto $dto): AgencyRentalContract
    {
        $agency = $this->agencyContext->requireAgency();
        $transport = $this->resolveTransport((string) $dto->transport, $agency->getId());
        $startAt = $this->parseDateTime((string) $dto->startAt, 'startAt');
        $endAt = $this->parseDateTime((string) $dto->endAt, 'endAt');
        $this->assertValidPeriod($startAt, $endAt);

        $currency = strtoupper((string) ($dto->currency ?? $agency->getDefaultCurrency()));
        if (!$agency->supportsCurrency($currency)) {
            throw new UnprocessableEntityException(sprintf('Currency "%s" is not supported by this agency.', $currency));
        }

        $contract = new AgencyRentalContract();
        $contract->setAgency($agency);
        $contract->setTransport($transport);
        $contract->setDriver($this->drivers->resolveForAssignment($dto->driver, $agency->getId()));
        $contract->setClientName((string) $dto->clientName);
        $contract->setClientPhone((string) $dto->clientPhone);
        $contract->setClientCompany($dto->clientCompany);
        $contract->setStartAt($startAt);
        $contract->setEndAt($endAt);
        $contract->setPickupLocation($dto->pickupLocation);
        $contract->setDropoffLocation($dto->dropoffLocation);
        $contract->setDailyRate((int) $dto->dailyRate);
        $contract->setTotalAmount((int) $dto->totalAmount);
        $contract->setDepositAmount($dto->depositAmount);
        $contract->setCurrency($currency);
        $contract->setStatus(AgencyRentalContract::STATUS_DRAFT);
        $contract->setNotes($dto->notes);

        $this->em->persist($contract);
        $this->em->flush();

        return $contract;
    }

    public function update(AgencyRentalContract $contract, UpdateAgencyRentalContractDto $dto): AgencyRentalContract
    {
        $this->agencyContext->assertOwns($contract->getAgency());
        $this->assertDraft($contract);

        $agencyId = $contract->getAgency()?->getId();

        if (null !== $dto->transport) {
            $contract->setTransport($this->resolveTransport($dto->transport, $agencyId));
        }
        if (null !== $dto->driver) {
            $contract->setDriver($this->drivers->resolveForAssignment($dto->driver, $agencyId));
        }
        if (null !== $dto->clientName) {
            $contract->setClientName($dto->clientName);
        }
        if (null !== $dto->clientPhone) {
            $contract->setClientPhone($dto->clientPhone);
        }
        if (null !== $dto->clientCompany) {
            $contract->setClientCompany($dto->clientCompany);
        }
        if (null !== $dto->startAt) {
            $contract->setStartAt($this->parseDateTime($dto->startAt, 'startAt'));
        }
        if (null !== $dto->endAt) {
            $contract->setEndAt($this->parseDateTime($dto->endAt, 'endAt'));
        }
        if (null !== $dto->pickupLocation) {
            $contract->setPickupLocation($dto->pickupLocation);
        }
        if (null !== $dto->dropoffLocation) {
            $contract->setDropoffLocation($dto->dropoffLocation);
        }
        if (null !== $dto->dailyRate) {
            $contract->setDailyRate($dto->dailyRate);
        }
        if (null !== $dto->totalAmount) {
            $contract->setTotalAmount($dto->totalAmount);
        }
        if (null !== $dto->depositAmount) {
            $contract->setDepositAmount($dto->depositAmount);
        }
        if (null !== $dto->currency) {
            $currency = strtoupper($dto->currency);
            $agency = $contract->getAgency();
            if (!$agency?->supportsCurrency($currency)) {
                throw new UnprocessableEntityException(sprintf('Currency "%s" is not supported by this agency.', $currency));
            }
            $contract->setCurrency($currency);
        }
        if (null !== $dto->notes) {
            $contract->setNotes($dto->notes);
        }

        $startAt = $contract->getStartAt();
        $endAt = $contract->getEndAt();
        if ($startAt instanceof \DateTimeImmutable && $endAt instanceof \DateTimeImmutable) {
            $this->assertValidPeriod($startAt, $endAt);
        }

        $this->em->flush();

        return $contract;
    }

    public function confirm(AgencyRentalContract $contract): AgencyRentalContract
    {
        $this->agencyContext->assertOwns($contract->getAgency());
        $this->assertDraft($contract);

        $transport = $contract->getTransport();
        $startAt = $contract->getStartAt();
        $endAt = $contract->getEndAt();
        if (!$transport instanceof AgencyTransport || !$startAt instanceof \DateTimeImmutable || !$endAt instanceof \DateTimeImmutable) {
            throw new UnprocessableEntityException('Rental contract is incomplete.');
        }

        if ($this->contracts->hasOverlap($transport, $startAt, $endAt, $contract->getId())) {
            throw new ConflictException('Transport already has a confirmed or active rental overlapping this period.');
        }

        $contract->setStatus(AgencyRentalContract::STATUS_CONFIRMED);
        $contract->setConfirmedAt(new \DateTimeImmutable('now'));

        $this->em->flush();

        return $contract;
    }

    public function activate(AgencyRentalContract $contract): AgencyRentalContract
    {
        $this->agencyContext->assertOwns($contract->getAgency());

        if (AgencyRentalContract::STATUS_CONFIRMED !== $contract->getStatus()) {
            throw new UnprocessableEntityException('Only CONFIRMED rental contracts can be activated.');
        }

        $contract->setStatus(AgencyRentalContract::STATUS_ACTIVE);
        $contract->setActivatedAt(new \DateTimeImmutable('now'));

        $this->em->flush();

        return $contract;
    }

    public function returnContract(AgencyRentalContract $contract): AgencyRentalContract
    {
        $this->agencyContext->assertOwns($contract->getAgency());

        if (AgencyRentalContract::STATUS_ACTIVE !== $contract->getStatus()) {
            throw new UnprocessableEntityException('Only ACTIVE rental contracts can be returned.');
        }

        $contract->setStatus(AgencyRentalContract::STATUS_RETURNED);
        $contract->setReturnedAt(new \DateTimeImmutable('now'));

        $this->em->flush();

        return $contract;
    }

    public function cancel(AgencyRentalContract $contract): AgencyRentalContract
    {
        $this->agencyContext->assertOwns($contract->getAgency());

        if (!\in_array($contract->getStatus(), [
            AgencyRentalContract::STATUS_DRAFT,
            AgencyRentalContract::STATUS_CONFIRMED,
        ], true)) {
            throw new UnprocessableEntityException('Only DRAFT or CONFIRMED rental contracts can be cancelled.');
        }

        $contract->setStatus(AgencyRentalContract::STATUS_CANCELLED);

        $this->em->flush();

        return $contract;
    }

    public function getOwned(string $id): AgencyRentalContract
    {
        $contract = $this->contracts->find($id);
        if (!$contract instanceof AgencyRentalContract) {
            throw new UnavailableDataException('Rental contract not found.');
        }

        $this->agencyContext->assertOwns($contract->getAgency());

        return $contract;
    }

    private function assertDraft(AgencyRentalContract $contract): void
    {
        if (AgencyRentalContract::STATUS_DRAFT !== $contract->getStatus()) {
            throw new UnprocessableEntityException('Only DRAFT rental contracts can be modified.');
        }
    }

    private function assertValidPeriod(\DateTimeImmutable $startAt, \DateTimeImmutable $endAt): void
    {
        if ($endAt <= $startAt) {
            throw new UnprocessableEntityException('endAt must be after startAt.');
        }
    }

    private function resolveTransport(string $ref, ?string $agencyId): AgencyTransport
    {
        $id = $this->extractId($ref);
        $transport = $this->transports->find($id);
        if (!$transport instanceof AgencyTransport || $transport->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException('Transport not found.');
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

    private function parseDateTime(string $value, string $field): \DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            throw new UnprocessableEntityException(sprintf('Invalid %s, expected datetime.', $field));
        }

        $formats = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed;
            }
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new UnprocessableEntityException(sprintf('Invalid %s, expected datetime.', $field));
        }
    }
}
