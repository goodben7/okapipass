<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyStaffDto;
use App\Manager\AgencyStaffManager;

/** @implements ProcessorInterface<CreateAgencyStaffDto, \App\Entity\AgencyStaffMember> */
final class CreateAgencyStaffProcessor implements ProcessorInterface
{
    public function __construct(private AgencyStaffManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyStaffDto);

        return $this->manager->create($data);
    }
}
