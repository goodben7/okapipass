<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdatePassDeclarationStatusDto;
use App\Exception\UnavailableDataException;
use App\Manager\PassDeclarationManager;
use App\Repository\PassDeclarationRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UpdatePassDeclarationStatusDto, \App\Entity\PassDeclaration> */
final class UpdatePassDeclarationStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private PassDeclarationManager $manager,
        private PassDeclarationRepository $declarations,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdatePassDeclarationStatusDto);
        $declaration = $this->declarations->find($uriVariables['id'] ?? null);
        if (null === $declaration) {
            throw new UnavailableDataException('Declaration not found.');
        }
        $this->agencyContext->assertOwns($declaration->getAgency());

        return $this->manager->updateStatus($declaration, (string) $data->status);
    }
}
