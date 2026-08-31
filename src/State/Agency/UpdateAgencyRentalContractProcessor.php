<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyRentalContractDto;
use App\Entity\AgencyRentalContract;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyRentalContractManager;
use App\Repository\AgencyRentalContractRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UpdateAgencyRentalContractDto, AgencyRentalContract> */
final class UpdateAgencyRentalContractProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyRentalContractManager $manager,
        private AgencyRentalContractRepository $contracts,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyRentalContractDto);

        $contract = $this->contracts->find($uriVariables['id'] ?? null);
        if (!$contract instanceof AgencyRentalContract) {
            throw new UnavailableDataException('Rental contract not found.');
        }
        $this->agencyContext->assertOwns($contract->getAgency());

        return $this->manager->update($contract, $data);
    }
}
