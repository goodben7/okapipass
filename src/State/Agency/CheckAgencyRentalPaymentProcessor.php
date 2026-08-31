<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyPayment;
use App\Entity\AgencyRentalContract;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyRentalPaymentManager;
use App\Repository\AgencyRentalContractRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<null, AgencyPayment> */
final class CheckAgencyRentalPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyRentalPaymentManager $manager,
        private AgencyRentalContractRepository $contracts,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return $this->manager->checkPayment($this->requireContract($uriVariables));
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
