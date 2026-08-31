<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyRentalContract;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyRentalContractManager;
use App\Repository\AgencyRentalContractRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<null, AgencyRentalContract> */
final class ConfirmAgencyRentalContractProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyRentalContractManager $manager,
        private AgencyRentalContractRepository $contracts,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return $this->manager->confirm($this->requireContract($uriVariables));
    }

    /** @param array<string, mixed> $uriVariables */
    private function requireContract(array $uriVariables): AgencyRentalContract
    {
        $contract = $this->contracts->find($uriVariables['id'] ?? null);
        if (!$contract instanceof AgencyRentalContract) {
            throw new UnavailableDataException('Rental contract not found.');
        }
        $this->agencyContext->assertOwns($contract->getAgency());

        return $contract;
    }
}
