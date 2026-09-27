<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateSchoolStudentDto;
use App\Entity\SchoolStudent;
use App\Manager\SchoolStudentManager;

/** @implements ProcessorInterface<CreateSchoolStudentDto, SchoolStudent> */
final class CreateSchoolStudentProcessor implements ProcessorInterface
{
    public function __construct(private SchoolStudentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolStudent
    {
        \assert($data instanceof CreateSchoolStudentDto);

        return $this->manager->create($data);
    }
}
