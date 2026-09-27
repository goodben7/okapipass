<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateSchoolContractDto;
use App\Entity\SchoolContract;
use App\Manager\SchoolContractManager;

/** @implements ProcessorInterface<UpdateSchoolContractDto, SchoolContract> */
final class UpdateSchoolContractProcessor implements ProcessorInterface
{
    public function __construct(private SchoolContractManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolContract
    {
        $contract = $context['previous_data'] ?? null;
        if (!$contract instanceof SchoolContract) {
            throw new \InvalidArgumentException('Expected SchoolContract as previous_data.');
        }
        \assert($data instanceof UpdateSchoolContractDto);

        return $this->manager->update($contract, $data);
    }
}
