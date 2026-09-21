<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyObligationDto;
use App\Entity\AgencyObligation;
use App\Manager\AgencyObligationManager;

/** @implements ProcessorInterface<UpdateAgencyObligationDto, AgencyObligation> */
final class UpdateAgencyObligationProcessor implements ProcessorInterface
{
    public function __construct(private AgencyObligationManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyObligation
    {
        \assert($data instanceof UpdateAgencyObligationDto);
        $obligation = $context['previous_data'] ?? null;
        \assert($obligation instanceof AgencyObligation);

        return $this->manager->update($obligation, $data);
    }
}
