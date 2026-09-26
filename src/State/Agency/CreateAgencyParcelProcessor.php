<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyParcelDto;
use App\Entity\AgencyParcel;
use App\Manager\AgencyParcelManager;

/** @implements ProcessorInterface<CreateAgencyParcelDto, AgencyParcel> */
final class CreateAgencyParcelProcessor implements ProcessorInterface
{
    public function __construct(private AgencyParcelManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyParcel
    {
        \assert($data instanceof CreateAgencyParcelDto);

        return $this->manager->create($data);
    }
}
