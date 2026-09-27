<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SchoolContract;
use App\Exception\UnavailableDataException;
use App\Manager\SchoolContractManager;
use App\Repository\SchoolContractRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<SchoolContract|null, void> */
final class DeleteSchoolContractProcessor implements ProcessorInterface
{
    public function __construct(
        private SchoolContractManager $manager,
        private SchoolContractRepository $contracts,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $contract = $data instanceof SchoolContract
            ? $data
            : $this->contracts->find($uriVariables['id'] ?? null);

        if (!$contract instanceof SchoolContract) {
            throw new UnavailableDataException('School contract not found.');
        }

        $this->agencyContext->assertOwns($contract->getAgency());
        $this->manager->delete($contract);

        return null;
    }
}
