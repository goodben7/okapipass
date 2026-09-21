<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\RejectOntFptDeclarationDto;
use App\Entity\PassDeclaration;
use App\Exception\UnavailableDataException;
use App\Manager\PassDeclarationManager;
use App\Repository\PassDeclarationRepository;

/** @implements ProcessorInterface<RejectOntFptDeclarationDto|null, PassDeclaration> */
final class RejectOntFptDeclarationProcessor implements ProcessorInterface
{
    public function __construct(
        private PassDeclarationRepository $declarations,
        private PassDeclarationManager $manager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PassDeclaration
    {
        $previous = $context['previous_data'] ?? null;
        $declaration = $previous instanceof PassDeclaration
            ? $previous
            : $this->declarations->find($uriVariables['id'] ?? null);
        if (null === $declaration) {
            throw new UnavailableDataException('Declaration not found.');
        }

        if (PassDeclaration::STATUS_REJECTED === $declaration->getStatus()) {
            return $declaration;
        }

        $reason = $data instanceof RejectOntFptDeclarationDto ? $data->reason : null;

        return $this->manager->rejectForOnt($declaration, $reason);
    }
}
