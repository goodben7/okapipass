<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyWorkOrderDto;
use App\Entity\AgencyWorkOrder;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyWorkOrderManager;
use App\Repository\AgencyWorkOrderRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UpdateAgencyWorkOrderDto, AgencyWorkOrder> */
final class UpdateAgencyWorkOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyWorkOrderManager $manager,
        private AgencyWorkOrderRepository $workOrders,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyWorkOrderDto);

        $workOrder = $this->workOrders->find($uriVariables['id'] ?? null);
        if (!$workOrder instanceof AgencyWorkOrder) {
            throw new UnavailableDataException('Work order not found.');
        }
        $this->agencyContext->assertOwns($workOrder->getAgency());

        return $this->manager->update($workOrder, $data);
    }
}
