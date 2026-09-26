<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\DrawSurprisePoolDto;
use App\Entity\SurprisePool;
use App\Entity\SurprisePoolItem;
use App\Manager\LoyaltyPointsManager;

/** @implements ProcessorInterface<DrawSurprisePoolDto, array<string, mixed>> */
final class DrawSurprisePoolProcessor implements ProcessorInterface
{
    public function __construct(private LoyaltyPointsManager $loyaltyPoints)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $pool = $context['previous_data'] ?? null;
        if (!$pool instanceof SurprisePool) {
            throw new \InvalidArgumentException('Expected SurprisePool as previous_data.');
        }

        $userId = $data instanceof DrawSurprisePoolDto ? $data->userId : null;
        $result = $this->loyaltyPoints->drawFromPool($pool, $userId);

        /** @var SurprisePoolItem $item */
        $item = $result['item'];

        return [
            'poolId' => $pool->getId(),
            'itemId' => $item->getId(),
            'label' => $item->getLabel(),
            'rewardType' => $item->getRewardType(),
            'rewardValue' => $item->getRewardValue(),
            'pointsCredited' => $result['pointsCredited'] ?? null,
            'accountId' => $result['accountId'] ?? null,
        ];
    }
}
