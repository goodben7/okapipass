<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyWorkOrderDto;
use App\Dto\Agency\UpdateAgencyWorkOrderDto;
use App\Entity\Agency;
use App\Entity\AgencyMaintenanceCase;
use App\Entity\AgencyTransport;
use App\Entity\AgencyWorkOrder;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyMaintenanceCaseRepository;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use App\Service\Agency\AgencyTransportMaintenanceSync;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyWorkOrderManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyTransportRepository $transports,
        private AgencyMaintenanceCaseRepository $maintenanceCases,
        private AgencyTransportMaintenanceSync $transportSync,
    ) {
    }

    public function create(CreateAgencyWorkOrderDto $dto): AgencyWorkOrder
    {
        $this->agencyContext->requirePermission(AgencyPermission::MAINTENANCE_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $transport = $this->resolveTransport((string) $dto->transport, $agency);

        $workOrder = new AgencyWorkOrder();
        $workOrder->setAgency($agency);
        $workOrder->setTransport($transport);
        $workOrder->setMaintenanceCase($this->resolveMaintenanceCase($dto->maintenanceCase, $agency));
        $workOrder->setTitle((string) $dto->title);
        $workOrder->setDescription($dto->description);
        $workOrder->setPartsCost($dto->partsCost ?? 0);
        $workOrder->setLaborCost($dto->laborCost ?? 0);
        $workOrder->setImmobilize($dto->immobilize ?? false);
        $workOrder->setVendorName($dto->vendorName);
        $workOrder->setStatus(AgencyWorkOrder::STATUS_OPEN);

        $this->em->persist($workOrder);
        $this->em->flush();
        $this->transportSync->syncTransportMaintenanceStatus($transport);
        $this->em->flush();

        return $workOrder;
    }

    public function update(AgencyWorkOrder $workOrder, UpdateAgencyWorkOrderDto $dto): AgencyWorkOrder
    {
        $this->agencyContext->requirePermission(AgencyPermission::MAINTENANCE_WRITE);
        $this->agencyContext->assertOwns($workOrder->getAgency());
        $this->assertMutable($workOrder);

        if (null !== $dto->title) {
            $workOrder->setTitle($dto->title);
        }
        if (null !== $dto->description) {
            $workOrder->setDescription($dto->description);
        }
        if (null !== $dto->partsCost) {
            $workOrder->setPartsCost($dto->partsCost);
        }
        if (null !== $dto->laborCost) {
            $workOrder->setLaborCost($dto->laborCost);
        }
        if (null !== $dto->immobilize) {
            $workOrder->setImmobilize($dto->immobilize);
        }
        if (null !== $dto->vendorName) {
            $workOrder->setVendorName($dto->vendorName);
        }

        $this->em->flush();
        $transport = $workOrder->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->transportSync->syncTransportMaintenanceStatus($transport);
        }
        $this->em->flush();

        return $workOrder;
    }

    public function start(AgencyWorkOrder $workOrder): AgencyWorkOrder
    {
        $this->agencyContext->requirePermission(AgencyPermission::MAINTENANCE_WRITE);
        $this->agencyContext->assertOwns($workOrder->getAgency());
        $this->assertMutable($workOrder);

        if (AgencyWorkOrder::STATUS_OPEN !== $workOrder->getStatus()) {
            throw new UnprocessableEntityException('Only OPEN work orders can be started.');
        }

        $workOrder->setStatus(AgencyWorkOrder::STATUS_IN_PROGRESS);
        $workOrder->setStartedAt(new \DateTimeImmutable('now'));

        $this->em->flush();
        $transport = $workOrder->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->transportSync->syncTransportMaintenanceStatus($transport);
        }
        $this->em->flush();

        return $workOrder;
    }

    public function complete(AgencyWorkOrder $workOrder): AgencyWorkOrder
    {
        $this->agencyContext->requirePermission(AgencyPermission::MAINTENANCE_WRITE);
        $this->agencyContext->assertOwns($workOrder->getAgency());
        $this->assertMutable($workOrder);

        $workOrder->setStatus(AgencyWorkOrder::STATUS_DONE);
        $workOrder->setCompletedAt(new \DateTimeImmutable('now'));
        $workOrder->setStartedAt($workOrder->getStartedAt() ?? $workOrder->getCompletedAt());

        $this->em->flush();
        $transport = $workOrder->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->transportSync->syncTransportMaintenanceStatus($transport);
        }
        $this->em->flush();

        return $workOrder;
    }

    public function cancel(AgencyWorkOrder $workOrder): AgencyWorkOrder
    {
        $this->agencyContext->requirePermission(AgencyPermission::MAINTENANCE_WRITE);
        $this->agencyContext->assertOwns($workOrder->getAgency());
        $this->assertMutable($workOrder);

        $workOrder->setStatus(AgencyWorkOrder::STATUS_CANCELLED);
        $workOrder->setCompletedAt(new \DateTimeImmutable('now'));

        $this->em->flush();
        $transport = $workOrder->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->transportSync->syncTransportMaintenanceStatus($transport);
        }
        $this->em->flush();

        return $workOrder;
    }

    private function assertMutable(AgencyWorkOrder $workOrder): void
    {
        if (\in_array($workOrder->getStatus(), [AgencyWorkOrder::STATUS_DONE, AgencyWorkOrder::STATUS_CANCELLED], true)) {
            throw new UnprocessableEntityException('Closed work orders cannot be modified.');
        }
    }

    private function resolveTransport(string $ref, Agency $agency): AgencyTransport
    {
        $transport = $this->transports->find($this->extractId($ref));
        if (!$transport instanceof AgencyTransport || $transport->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Transport not found.');
        }

        return $transport;
    }

    private function resolveMaintenanceCase(?string $ref, Agency $agency): ?AgencyMaintenanceCase
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $case = $this->maintenanceCases->find($this->extractId($ref));
        if (!$case instanceof AgencyMaintenanceCase || $case->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Maintenance case not found.');
        }

        return $case;
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
