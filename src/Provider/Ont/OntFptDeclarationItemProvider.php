<?php

namespace App\Provider\Ont;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\PassDeclaration;
use App\Exception\UnavailableDataException;
use App\Repository\PassDeclarationRepository;

/** @implements ProviderInterface<PassDeclaration> */
final class OntFptDeclarationItemProvider implements ProviderInterface
{
    public function __construct(private PassDeclarationRepository $declarations)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PassDeclaration
    {
        $declaration = $this->declarations->find($uriVariables['id'] ?? null);
        if (!$declaration instanceof PassDeclaration) {
            throw new UnavailableDataException('Declaration not found.');
        }

        return $declaration;
    }
}
