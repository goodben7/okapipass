<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateSurprisePoolDto;
use App\Entity\SurprisePool;
use App\Manager\LoyaltyPointsManager;

/** @implements ProcessorInterface<CreateSurprisePoolDto, SurprisePool> */
final class CreateSurprisePoolProcessor implements ProcessorInterface
{
    public function __construct(private LoyaltyPointsManager $loyaltyPoints)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SurprisePool
    {
        if (!$data instanceof CreateSurprisePoolDto) {
            throw new \InvalidArgumentException('Expected CreateSurprisePoolDto.');
        }

        return $this->loyaltyPoints->createPool($data);
    }
}
