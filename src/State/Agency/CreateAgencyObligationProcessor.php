<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyObligationDto;
use App\Entity\AgencyObligation;
use App\Manager\AgencyObligationManager;

/** @implements ProcessorInterface<CreateAgencyObligationDto, AgencyObligation> */
final class CreateAgencyObligationProcessor implements ProcessorInterface
{
    public function __construct(private AgencyObligationManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyObligation
    {
        \assert($data instanceof CreateAgencyObligationDto);

        return $this->manager->create($data);
    }
}
