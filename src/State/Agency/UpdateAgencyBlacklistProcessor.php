<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyBlacklistDto;
use App\Entity\AgencyBlacklistEntry;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyBlacklistManager;

/** @implements ProcessorInterface<UpdateAgencyBlacklistDto, AgencyBlacklistEntry> */
final class UpdateAgencyBlacklistProcessor implements ProcessorInterface
{
    public function __construct(private AgencyBlacklistManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyBlacklistEntry
    {
        $entity = $context['previous_data'] ?? $data;
        if (!$entity instanceof AgencyBlacklistEntry) {
            throw new UnavailableDataException('AgencyBlacklistEntry not found.');
        }
        \assert($data instanceof UpdateAgencyBlacklistDto);

        return $this->manager->update($entity, $data);
    }
}
