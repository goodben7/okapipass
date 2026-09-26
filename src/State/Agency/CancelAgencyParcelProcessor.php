<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AgencyParcel;
use App\Manager\AgencyParcelManager;

/** @implements ProcessorInterface<mixed, AgencyParcel> */
final class CancelAgencyParcelProcessor implements ProcessorInterface
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

        return $this->manager->cancel($parcel);
    }
}
