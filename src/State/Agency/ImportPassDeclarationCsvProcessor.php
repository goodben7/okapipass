<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\ImportPassDeclarationCsvDto;
use App\Manager\PassDeclarationManager;

/** @implements ProcessorInterface<ImportPassDeclarationCsvDto, \App\Entity\PassDeclaration> */
final class ImportPassDeclarationCsvProcessor implements ProcessorInterface
{
    public function __construct(private PassDeclarationManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof ImportPassDeclarationCsvDto);

        return $this->manager->importCsv($data);
    }
}
