<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateSurprisePoolDto;
use App\Entity\SurprisePool;
use App\Manager\LoyaltyPointsManager;

/** @implements ProcessorInterface<UpdateSurprisePoolDto, SurprisePool> */
final class UpdateSurprisePoolProcessor implements ProcessorInterface
{
    public function __construct(private LoyaltyPointsManager $loyaltyPoints)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SurprisePool
    {
        if (!$data instanceof UpdateSurprisePoolDto) {
            throw new \InvalidArgumentException('Expected UpdateSurprisePoolDto.');
        }

        $pool = $context['previous_data'] ?? null;
        if (!$pool instanceof SurprisePool) {
            throw new \InvalidArgumentException('Expected SurprisePool as previous_data.');
        }

        return $this->loyaltyPoints->updatePool($pool, $data);
    }
}
