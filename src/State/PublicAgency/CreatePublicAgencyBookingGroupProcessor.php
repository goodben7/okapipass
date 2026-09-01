<?php

namespace App\State\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Public\PublicAgencyBookingGroupResource;
use App\Dto\Public\CreatePublicAgencyBookingGroupDto;
use App\Manager\PublicAgencyGroupBookingManager;

/** @implements ProcessorInterface<CreatePublicAgencyBookingGroupDto, PublicAgencyBookingGroupResource> */
final class CreatePublicAgencyBookingGroupProcessor implements ProcessorInterface
{
    public function __construct(private PublicAgencyGroupBookingManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublicAgencyBookingGroupResource
    {
        \assert($data instanceof CreatePublicAgencyBookingGroupDto);

        return $this->manager->createOnline($data);
    }
}
