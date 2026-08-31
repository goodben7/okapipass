<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyDriver;
use App\Manager\AgencyDriverManager;

/** @implements ProcessorInterface<null, void> */
final class DeleteAgencyDriverProcessor implements ProcessorInterface
{
    public function __construct(private AgencyDriverManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var AgencyDriver|null $driver */
        $driver = $context['previous_data'] ?? null;
        if (!$driver instanceof AgencyDriver) {
            $driver = $this->manager->getOwned((string) ($uriVariables['id'] ?? ''));
        }

        $this->manager->delete($driver);

        return null;
    }
}
