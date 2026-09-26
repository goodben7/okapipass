<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyDriverDocument;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyDriverDocumentManager;

/** @implements ProcessorInterface<AgencyDriverDocument|null, void> */
final class DeleteAgencyDriverDocumentProcessor implements ProcessorInterface
{
    public function __construct(private AgencyDriverDocumentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof AgencyDriverDocument) {
            throw new UnavailableDataException('Document not found.');
        }

        $this->manager->delete($data);
    }
}
