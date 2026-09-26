<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyDriverDocumentDto;
use App\Entity\AgencyDriverDocument;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyDriverDocumentManager;

/** @implements ProcessorInterface<UpdateAgencyDriverDocumentDto, AgencyDriverDocument> */
final class UpdateAgencyDriverDocumentProcessor implements ProcessorInterface
{
    public function __construct(private AgencyDriverDocumentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyDriverDocument
    {
        $entity = $context['previous_data'] ?? $data;
        if (!$entity instanceof AgencyDriverDocument) {
            throw new UnavailableDataException('AgencyDriverDocument not found.');
        }
        \assert($data instanceof UpdateAgencyDriverDocumentDto);

        return $this->manager->update($entity, $data);
    }
}
