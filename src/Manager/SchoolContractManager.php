<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateSchoolContractDto;
use App\Dto\Agency\UpdateSchoolContractDto;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use App\Entity\SchoolContract;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyTransportRepository;
use App\Repository\SchoolContractRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class SchoolContractManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private SchoolContractRepository $contracts,
        private AgencyOfferRepository $offers,
        private AgencyTransportRepository $transports,
    ) {
    }

    public function create(CreateSchoolContractDto $dto): SchoolContract
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $offer = $this->resolveOffer((string) $dto->offer, $agency->getId());
        $this->assertSchoolOffer($offer);

        $startDate = $this->parseDate((string) $dto->startDate, 'startDate');
        $endDate = $this->parseDate((string) $dto->endDate, 'endDate');
        $this->assertValidPeriod($startDate, $endDate);

        $currency = strtoupper((string) ($dto->currency ?? $agency->getDefaultCurrency()));
        if (!$agency->supportsCurrency($currency)) {
            throw new UnprocessableEntityException(sprintf('Currency "%s" is not supported by this agency.', $currency));
        }

        $contract = new SchoolContract();
        $contract->setAgency($agency);
        $contract->setSchoolName((string) $dto->schoolName);
        $contract->setSchoolPhone($dto->schoolPhone);
        $contract->setSchoolAddress($dto->schoolAddress);
        $contract->setOffer($offer);
        $contract->setTransport($this->resolveTransport($dto->transport, $agency->getId()));
        $contract->setStartDate($startDate);
        $contract->setEndDate($endDate);
        $contract->setStatus($dto->status ?? SchoolContract::STATUS_DRAFT);
        $contract->setMonthlyFee((int) ($dto->monthlyFee ?? 0));
        $contract->setCurrency($currency);
        $contract->setStops($this->normalizeStops($dto->stops));
        $contract->setNotes($dto->notes);

        $this->em->persist($contract);
        $this->em->flush();

        return $contract;
    }

    public function update(SchoolContract $contract, UpdateSchoolContractDto $dto): SchoolContract
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $this->agencyContext->assertOwns($contract->getAgency());
        $agencyId = $contract->getAgency()?->getId();

        if (null !== $dto->schoolName) {
            $contract->setSchoolName($dto->schoolName);
        }
        if (null !== $dto->schoolPhone) {
            $contract->setSchoolPhone($dto->schoolPhone);
        }
        if (null !== $dto->schoolAddress) {
            $contract->setSchoolAddress($dto->schoolAddress);
        }
        if (null !== $dto->offer) {
            $offer = $this->resolveOffer($dto->offer, $agencyId);
            $this->assertSchoolOffer($offer);
            $contract->setOffer($offer);
        }
        if (null !== $dto->transport) {
            $contract->setTransport($this->resolveTransport($dto->transport, $agencyId));
        }
        if (null !== $dto->startDate) {
            $contract->setStartDate($this->parseDate($dto->startDate, 'startDate'));
        }
        if (null !== $dto->endDate) {
            $contract->setEndDate($this->parseDate($dto->endDate, 'endDate'));
        }
        if (null !== $dto->status) {
            $contract->setStatus($dto->status);
        }
        if (null !== $dto->monthlyFee) {
            $contract->setMonthlyFee($dto->monthlyFee);
        }
        if (null !== $dto->currency) {
            $currency = strtoupper($dto->currency);
            if (!$contract->getAgency()?->supportsCurrency($currency)) {
                throw new UnprocessableEntityException(sprintf('Currency "%s" is not supported by this agency.', $currency));
            }
            $contract->setCurrency($currency);
        }
        if (null !== $dto->stops) {
            $contract->setStops($this->normalizeStops($dto->stops));
        }
        if (null !== $dto->notes) {
            $contract->setNotes($dto->notes);
        }

        $startDate = $contract->getStartDate();
        $endDate = $contract->getEndDate();
        if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable) {
            $this->assertValidPeriod($startDate, $endDate);
        }

        $this->em->flush();

        return $contract;
    }

    public function delete(SchoolContract $contract): void
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $this->agencyContext->assertOwns($contract->getAgency());
        $this->em->remove($contract);
        $this->em->flush();
    }

    public function requireOwnedContract(string $contractId): SchoolContract
    {
        $agency = $this->agencyContext->requireAgency();
        $contract = $this->contracts->find($this->extractId($contractId));
        if (!$contract instanceof SchoolContract || $contract->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException(sprintf('School contract "%s" not found.', $contractId));
        }

        return $contract;
    }

    private function assertSchoolOffer(AgencyOffer $offer): void
    {
        if (!$offer->isSchoolService()) {
            throw new UnprocessableEntityException('School contracts require an offer with serviceType=SCHOOL.');
        }
    }

    private function resolveOffer(string $ref, ?string $agencyId): AgencyOffer
    {
        $id = $this->extractId($ref);
        $offer = $this->offers->find($id);
        if (!$offer instanceof AgencyOffer || $offer->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('Offer "%s" not found.', $id));
        }

        return $offer;
    }

    private function resolveTransport(?string $ref, ?string $agencyId): ?AgencyTransport
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }
        $id = $this->extractId($ref);
        $transport = $this->transports->find($id);
        if (!$transport instanceof AgencyTransport || $transport->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('Transport "%s" not found.', $id));
        }

        return $transport;
    }

    /**
     * @param list<array{code?: string, label?: string, order?: int, time?: string}>|null $stops
     *
     * @return list<array{code: string, label: string, order: int, time?: string}>|null
     */
    private function normalizeStops(?array $stops): ?array
    {
        if (null === $stops) {
            return null;
        }
        if ([] === $stops) {
            return [];
        }

        $normalized = [];
        foreach ($stops as $index => $stop) {
            $code = trim((string) ($stop['code'] ?? ''));
            $label = trim((string) ($stop['label'] ?? ''));
            if ('' === $code || '' === $label) {
                throw new UnprocessableEntityException(sprintf('Stop at index %d must include code and label.', $index));
            }
            $row = [
                'code' => $code,
                'label' => $label,
                'order' => (int) ($stop['order'] ?? $index + 1),
            ];
            if (isset($stop['time']) && '' !== (string) $stop['time']) {
                $row['time'] = (string) $stop['time'];
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    private function parseDate(string $value, string $field): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false === $date) {
            throw new UnprocessableEntityException(sprintf('Invalid %s date.', $field));
        }

        return $date->setTime(0, 0);
    }

    private function assertValidPeriod(\DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        if ($end < $start) {
            throw new UnprocessableEntityException('endDate must be on or after startDate.');
        }
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
