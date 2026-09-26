<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyDriverDocumentDto;
use App\Entity\AgencyDriverDocument;
use App\Manager\AgencyDriverDocumentManager;

/** @implements ProcessorInterface<CreateAgencyDriverDocumentDto, AgencyDriverDocument> */
final class CreateAgencyDriverDocumentProcessor implements ProcessorInterface
{
    public function __construct(private AgencyDriverDocumentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyDriverDocument
    {
        \assert($data instanceof CreateAgencyDriverDocumentDto);

        return $this->manager->create($data);
    }
}
