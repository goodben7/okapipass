<?php

namespace App\Manager;

use App\Dto\Agency\CompleteAgencyMaintenanceCaseDto;
use App\Dto\Agency\CreateAgencyMaintenanceCaseDto;
use App\Dto\Agency\UpdateAgencyMaintenanceCaseDto;
use App\Entity\AgencyMaintenanceCase;
use App\Entity\AgencyTransport;
use App\Service\Agency\AgencyFleetNotifier;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyMaintenanceCaseRepository;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyMaintenanceCaseManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyMaintenanceCaseRepository $cases,
        private AgencyTransportRepository $transports,
        private AgencyFleetNotifier $fleetNotifier,
    ) {
    }

    public function create(CreateAgencyMaintenanceCaseDto $dto): AgencyMaintenanceCase
    {
        $agency = $this->agencyContext->requireAgency();
        $transport = $this->resolveTransport((string) $dto->transport, $agency->getId());

        $case = new AgencyMaintenanceCase();
        $case->setAgency($agency);
        $case->setTransport($transport);
        $case->setType((string) $dto->type);
        $case->setTitle((string) $dto->title);
        $case->setDescription($dto->description);
        $case->setOdometerKm($dto->odometerKm);
        $case->setEstimatedCost($dto->estimatedCost);
        $case->setVendorName($dto->vendorName);
        $case->setStatus(AgencyMaintenanceCase::STATUS_OPEN);
        $case->setReportedAt(new \DateTimeImmutable('now'));

        $this->em->persist($case);
        $this->em->flush();
        $this->syncTransportMaintenanceStatus($transport);
        $this->em->flush();

        $this->fleetNotifier->notifyMaintenanceOpened($case);

        return $case;
    }

    public function update(AgencyMaintenanceCase $case, UpdateAgencyMaintenanceCaseDto $dto): AgencyMaintenanceCase
    {
        $this->agencyContext->assertOwns($case->getAgency());
        $this->assertMutable($case);

        if (null !== $dto->type) {
            $case->setType($dto->type);
        }
        if (null !== $dto->title) {
            $case->setTitle($dto->title);
        }
        if (null !== $dto->description) {
            $case->setDescription($dto->description);
        }
        if (null !== $dto->odometerKm) {
            $case->setOdometerKm($dto->odometerKm);
        }
        if (null !== $dto->estimatedCost) {
            $case->setEstimatedCost($dto->estimatedCost);
        }
        if (null !== $dto->actualCost) {
            $case->setActualCost($dto->actualCost);
        }
        if (null !== $dto->vendorName) {
            $case->setVendorName($dto->vendorName);
        }
        if (null !== $dto->status) {
            $this->applyStatusTransition($case, $dto->status);
        }

        $this->em->flush();

        $transport = $case->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->syncTransportMaintenanceStatus($transport);
        }

        $this->em->flush();

        return $case;
    }

    public function start(AgencyMaintenanceCase $case): AgencyMaintenanceCase
    {
        $this->agencyContext->assertOwns($case->getAgency());
        $this->assertMutable($case);

        if (!\in_array($case->getStatus(), [
            AgencyMaintenanceCase::STATUS_OPEN,
            AgencyMaintenanceCase::STATUS_WAITING_PARTS,
        ], true)) {
            throw new UnprocessableEntityException('Only OPEN or WAITING_PARTS maintenance cases can be started.');
        }

        $case->setStatus(AgencyMaintenanceCase::STATUS_IN_PROGRESS);
        $case->setStartedAt($case->getStartedAt() ?? new \DateTimeImmutable('now'));

        $transport = $case->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->syncTransportMaintenanceStatus($transport);
        }

        $this->em->flush();

        return $case;
    }

    public function complete(AgencyMaintenanceCase $case, ?CompleteAgencyMaintenanceCaseDto $dto = null): AgencyMaintenanceCase
    {
        $this->agencyContext->assertOwns($case->getAgency());
        $this->assertMutable($case);

        if (!\in_array($case->getStatus(), [
            AgencyMaintenanceCase::STATUS_OPEN,
            AgencyMaintenanceCase::STATUS_IN_PROGRESS,
            AgencyMaintenanceCase::STATUS_WAITING_PARTS,
        ], true)) {
            throw new UnprocessableEntityException('Maintenance case is already closed.');
        }

        if ($dto instanceof CompleteAgencyMaintenanceCaseDto) {
            if (null !== $dto->actualCost) {
                $case->setActualCost($dto->actualCost);
            }
            if (null !== $dto->description) {
                $case->setDescription($dto->description);
            }
        }

        $case->setStatus(AgencyMaintenanceCase::STATUS_DONE);
        $case->setCompletedAt(new \DateTimeImmutable('now'));
        $case->setStartedAt($case->getStartedAt() ?? $case->getCompletedAt());

        $this->em->flush();

        $transport = $case->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->syncTransportMaintenanceStatus($transport);
        }

        $this->em->flush();

        return $case;
    }

    public function cancel(AgencyMaintenanceCase $case): AgencyMaintenanceCase
    {
        $this->agencyContext->assertOwns($case->getAgency());
        $this->assertMutable($case);

        $case->setStatus(AgencyMaintenanceCase::STATUS_CANCELLED);
        $case->setCompletedAt(new \DateTimeImmutable('now'));

        $this->em->flush();

        $transport = $case->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->syncTransportMaintenanceStatus($transport);
        }

        $this->em->flush();

        return $case;
    }

    public function getOwned(string $id): AgencyMaintenanceCase
    {
        $case = $this->cases->find($id);
        if (!$case instanceof AgencyMaintenanceCase) {
            throw new UnavailableDataException('Maintenance case not found.');
        }

        $this->agencyContext->assertOwns($case->getAgency());

        return $case;
    }

    public function syncTransportMaintenanceStatus(AgencyTransport $transport): void
    {
        if ($this->cases->countBlockingByTransport($transport) > 0) {
            if (AgencyTransport::STATUS_INACTIVE !== $transport->getStatus()) {
                $transport->setStatus(AgencyTransport::STATUS_MAINTENANCE);
            }

            return;
        }

        if (AgencyTransport::STATUS_MAINTENANCE === $transport->getStatus()) {
            $transport->setStatus(AgencyTransport::STATUS_ACTIVE);
        }
    }

    private function applyStatusTransition(AgencyMaintenanceCase $case, string $status): void
    {
        if (\in_array($case->getStatus(), [AgencyMaintenanceCase::STATUS_DONE, AgencyMaintenanceCase::STATUS_CANCELLED], true)) {
            throw new UnprocessableEntityException('Closed maintenance cases cannot change status.');
        }

        if (\in_array($status, [AgencyMaintenanceCase::STATUS_DONE, AgencyMaintenanceCase::STATUS_CANCELLED], true)) {
            throw new UnprocessableEntityException('Use complete or cancel actions to close a maintenance case.');
        }

        $case->setStatus($status);

        if (AgencyMaintenanceCase::STATUS_IN_PROGRESS === $status) {
            $case->setStartedAt($case->getStartedAt() ?? new \DateTimeImmutable('now'));
        }
    }

    private function assertMutable(AgencyMaintenanceCase $case): void
    {
        if (\in_array($case->getStatus(), [AgencyMaintenanceCase::STATUS_DONE, AgencyMaintenanceCase::STATUS_CANCELLED], true)) {
            throw new UnprocessableEntityException('Closed maintenance cases cannot be modified.');
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
}
