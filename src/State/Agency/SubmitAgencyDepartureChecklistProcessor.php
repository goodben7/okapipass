<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyDepartureChecklist;
use App\Manager\AgencyFleetOpsManager;

/** @implements ProcessorInterface<null, AgencyDepartureChecklist> */
final class SubmitAgencyDepartureChecklistProcessor implements ProcessorInterface
{
    public function __construct(private AgencyFleetOpsManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof AgencyDepartureChecklist);

        return $this->manager->submitDepartureChecklist($data);
    }
}
