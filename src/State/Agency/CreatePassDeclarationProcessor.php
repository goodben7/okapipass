<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreatePassDeclarationDto;
use App\Manager\PassDeclarationManager;

/** @implements ProcessorInterface<CreatePassDeclarationDto, \App\Entity\PassDeclaration> */
final class CreatePassDeclarationProcessor implements ProcessorInterface
{
    public function __construct(private PassDeclarationManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreatePassDeclarationDto);

        return $this->manager->createManual($data);
    }
}
