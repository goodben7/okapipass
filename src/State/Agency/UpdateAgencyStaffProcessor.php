<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyStaffDto;
use App\Entity\AgencyStaffMember;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyStaffManager;
use App\Repository\AgencyStaffMemberRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UpdateAgencyStaffDto, AgencyStaffMember> */
final class UpdateAgencyStaffProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyStaffManager $manager,
        private AgencyStaffMemberRepository $staffMembers,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyStaffDto);

        $member = $this->staffMembers->find($uriVariables['id'] ?? null);
        if (null === $member) {
            throw new UnavailableDataException('Staff member not found.');
        }
        $this->agencyContext->assertOwns($member->getAgency());

        return $this->manager->update($member, $data);
    }
}
