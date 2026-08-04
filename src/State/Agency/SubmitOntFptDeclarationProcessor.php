<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\SubmitOntFptDeclarationDto;
use App\Entity\PassDeclaration;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Manager\PassDeclarationManager;
use App\Repository\PassDeclarationRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<SubmitOntFptDeclarationDto, PassDeclaration> */
final class SubmitOntFptDeclarationProcessor implements ProcessorInterface
{
    public function __construct(
        private PassDeclarationRepository $declarations,
        private PassDeclarationManager $manager,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PassDeclaration
    {
        \assert($data instanceof SubmitOntFptDeclarationDto);
        $agency = $this->agencyContext->requireAgency();
        $id = $this->extractId((string) $data->declaration);
        $declaration = $this->declarations->find($id);
        if (null === $declaration || $declaration->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Declaration not found.');
        }

        if (PassDeclaration::STATUS_SUBMITTED === $declaration->getStatus()
            || PassDeclaration::STATUS_PAID === $declaration->getStatus()
        ) {
            return $declaration; // idempotent
        }

        if (PassDeclaration::STATUS_DRAFT !== $declaration->getStatus()) {
            throw new UnprocessableEntityException('Only draft declarations can be submitted to ONT.');
        }

        return $this->manager->updateStatus($declaration, PassDeclaration::STATUS_SUBMITTED);
    }

    private function extractId(string $ref): string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));

            return (string) end($parts);
        }

        return $ref;
    }
}
