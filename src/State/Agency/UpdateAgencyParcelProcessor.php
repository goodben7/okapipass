<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyParcelDto;
use App\Entity\AgencyParcel;
use App\Manager\AgencyParcelManager;

/** @implements ProcessorInterface<UpdateAgencyParcelDto, AgencyParcel> */
final class UpdateAgencyParcelProcessor implements ProcessorInterface
{
    public function __construct(private AgencyParcelManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgencyParcel
    {
        $parcel = $context['previous_data'] ?? null;
        if (!$parcel instanceof AgencyParcel) {
            throw new \InvalidArgumentException('Expected AgencyParcel as previous_data.');
        }
        \assert($data instanceof UpdateAgencyParcelDto);

        return $this->manager->update($parcel, $data);
    }
}
