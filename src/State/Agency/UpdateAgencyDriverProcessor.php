<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyDriverDto;
use App\Entity\AgencyDriver;
use App\Manager\AgencyDriverManager;

/** @implements ProcessorInterface<UpdateAgencyDriverDto, AgencyDriver> */
final class UpdateAgencyDriverProcessor implements ProcessorInterface
{
    public function __construct(private AgencyDriverManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyDriverDto);

        /** @var AgencyDriver $driver */
        $driver = $context['previous_data'] ?? null;
        if (!$driver instanceof AgencyDriver) {
            $driver = $this->manager->getOwned((string) ($uriVariables['id'] ?? ''));
        }

        return $this->manager->update($driver, $data);
    }
}
