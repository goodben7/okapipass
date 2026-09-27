<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateSchoolStudentDto;
use App\Entity\SchoolStudent;
use App\Manager\SchoolStudentManager;

/** @implements ProcessorInterface<UpdateSchoolStudentDto, SchoolStudent> */
final class UpdateSchoolStudentProcessor implements ProcessorInterface
{
    public function __construct(private SchoolStudentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolStudent
    {
        $student = $context['previous_data'] ?? null;
        if (!$student instanceof SchoolStudent) {
            throw new \InvalidArgumentException('Expected SchoolStudent as previous_data.');
        }
        \assert($data instanceof UpdateSchoolStudentDto);

        return $this->manager->update($student, $data);
    }
}
