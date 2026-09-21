<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PassDeclaration;
use App\Exception\UnavailableDataException;
use App\Manager\PassDeclarationManager;
use App\Repository\PassDeclarationRepository;

/** @implements ProcessorInterface<null, PassDeclaration> */
final class ValidateOntFptDeclarationProcessor implements ProcessorInterface
{
    public function __construct(
        private PassDeclarationRepository $declarations,
        private PassDeclarationManager $manager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PassDeclaration
    {
        $declaration = $data instanceof PassDeclaration
            ? $data
            : $this->declarations->find($uriVariables['id'] ?? null);
        if (null === $declaration) {
            throw new UnavailableDataException('Declaration not found.');
        }

        if (PassDeclaration::STATUS_VALIDATED === $declaration->getStatus()) {
            return $declaration;
        }

        return $this->manager->validateForOnt($declaration);
    }
}
