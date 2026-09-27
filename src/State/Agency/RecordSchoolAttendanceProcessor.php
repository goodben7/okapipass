<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\RecordSchoolAttendanceDto;
use App\Entity\SchoolAttendance;
use App\Manager\SchoolBusManager;

/** @implements ProcessorInterface<RecordSchoolAttendanceDto, SchoolAttendance> */
final class RecordSchoolAttendanceProcessor implements ProcessorInterface
{
    public function __construct(private SchoolBusManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolAttendance
    {
        \assert($data instanceof RecordSchoolAttendanceDto);

        return $this->manager->recordAttendance($data);
    }
}
