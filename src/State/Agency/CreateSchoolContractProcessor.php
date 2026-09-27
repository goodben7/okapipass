<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateSchoolContractDto;
use App\Entity\SchoolContract;
use App\Manager\SchoolContractManager;

/** @implements ProcessorInterface<CreateSchoolContractDto, SchoolContract> */
final class CreateSchoolContractProcessor implements ProcessorInterface
{
    public function __construct(private SchoolContractManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolContract
    {
        \assert($data instanceof CreateSchoolContractDto);

        return $this->manager->create($data);
    }
}
