<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyEmbarkationDto;
use App\Manager\AgencyEmbarkationManager;

/** @implements ProcessorInterface<CreateAgencyEmbarkationDto, \App\Entity\AgencyEmbarkation> */
final class CreateAgencyEmbarkationProcessor implements ProcessorInterface
{
    public function __construct(private AgencyEmbarkationManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyEmbarkationDto);

        return $this->manager->create($data);
    }
}
