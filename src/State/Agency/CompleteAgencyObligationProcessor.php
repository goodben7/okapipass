<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyObligation;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyObligationManager;

/** @implements ProcessorInterface<AgencyObligation|null, AgencyObligation> */
final class CompleteAgencyObligationProcessor implements ProcessorInterface
{
    public function __construct(private AgencyObligationManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyObligation
    {
        if (!$data instanceof AgencyObligation) {
            throw new UnavailableDataException('Obligation not found.');
        }

        return $this->manager->complete($data);
    }
}
