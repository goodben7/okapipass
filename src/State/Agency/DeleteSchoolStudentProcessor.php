<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SchoolStudent;
use App\Exception\UnavailableDataException;
use App\Manager\SchoolStudentManager;
use App\Repository\SchoolStudentRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<SchoolStudent|null, void> */
final class DeleteSchoolStudentProcessor implements ProcessorInterface
{
    public function __construct(
        private SchoolStudentManager $manager,
        private SchoolStudentRepository $students,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $student = $data instanceof SchoolStudent
            ? $data
            : $this->students->find($uriVariables['id'] ?? null);

        if (!$student instanceof SchoolStudent) {
            throw new UnavailableDataException('School student not found.');
        }

        $this->agencyContext->assertOwns($student->getAgency());
        $this->manager->delete($student);

        return null;
    }
}
