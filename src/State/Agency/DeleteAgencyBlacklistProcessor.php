<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyBlacklistEntry;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyBlacklistManager;

/** @implements ProcessorInterface<AgencyBlacklistEntry|null, void> */
final class DeleteAgencyBlacklistProcessor implements ProcessorInterface
{
    public function __construct(private AgencyBlacklistManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof AgencyBlacklistEntry) {
            throw new UnavailableDataException('Blacklist entry not found.');
        }

        $this->manager->delete($data);
    }
}
