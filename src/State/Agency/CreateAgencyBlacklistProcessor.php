<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyBlacklistDto;
use App\Entity\AgencyBlacklistEntry;
use App\Manager\AgencyBlacklistManager;

/** @implements ProcessorInterface<CreateAgencyBlacklistDto, AgencyBlacklistEntry> */
final class CreateAgencyBlacklistProcessor implements ProcessorInterface
{
    public function __construct(private AgencyBlacklistManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyBlacklistEntry
    {
        \assert($data instanceof CreateAgencyBlacklistDto);

        return $this->manager->create($data);
    }
}
