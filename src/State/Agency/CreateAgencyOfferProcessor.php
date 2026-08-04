<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyOfferDto;
use App\Manager\AgencyOfferManager;

/** @implements ProcessorInterface<CreateAgencyOfferDto, \App\Entity\AgencyOffer> */
final class CreateAgencyOfferProcessor implements ProcessorInterface
{
    public function __construct(private AgencyOfferManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof CreateAgencyOfferDto);

        return $this->manager->create($data);
    }
}
