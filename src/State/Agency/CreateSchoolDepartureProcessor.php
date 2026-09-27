<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateSchoolDepartureDto;
use App\Entity\AgencyEmbarkation;
use App\Manager\SchoolBusManager;

/** @implements ProcessorInterface<CreateSchoolDepartureDto, AgencyEmbarkation> */
final class CreateSchoolDepartureProcessor implements ProcessorInterface
{
    public function __construct(private SchoolBusManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyEmbarkation
    {
        \assert($data instanceof CreateSchoolDepartureDto);

        return $this->manager->createOrFindDeparture($data);
    }
}
