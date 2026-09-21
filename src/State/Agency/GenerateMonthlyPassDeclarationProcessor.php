<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\GenerateMonthlyPassDeclarationDto;
use App\Entity\PassDeclaration;
use App\Manager\PassDeclarationManager;

/** @implements ProcessorInterface<GenerateMonthlyPassDeclarationDto, PassDeclaration> */
final class GenerateMonthlyPassDeclarationProcessor implements ProcessorInterface
{
    public function __construct(private PassDeclarationManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PassDeclaration
    {
        \assert($data instanceof GenerateMonthlyPassDeclarationDto);

        return $this->manager->generateMonthly((string) $data->yearMonth);
    }
}
