<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyStaffMember;
use App\Manager\AgencyStaffManager;

/** @implements ProcessorInterface<AgencyStaffMember, void> */
final class DeleteAgencyStaffProcessor implements ProcessorInterface
{
    public function __construct(private AgencyStaffManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof AgencyStaffMember);
        $this->manager->delete($data);

        return null;
    }
}
