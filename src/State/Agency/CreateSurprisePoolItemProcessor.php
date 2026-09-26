<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateSurprisePoolItemDto;
use App\Entity\SurprisePool;
use App\Entity\SurprisePoolItem;
use App\Exception\UnprocessableEntityException;
use App\Manager\LoyaltyPointsManager;
use App\Repository\SurprisePoolRepository;

/** @implements ProcessorInterface<CreateSurprisePoolItemDto, SurprisePoolItem> */
final class CreateSurprisePoolItemProcessor implements ProcessorInterface
{
    public function __construct(
        private SurprisePoolRepository $pools,
        private LoyaltyPointsManager $loyaltyPoints,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SurprisePoolItem
    {
        if (!$data instanceof CreateSurprisePoolItemDto) {
            throw new \InvalidArgumentException('Expected CreateSurprisePoolItemDto.');
        }

        $poolId = (string) ($uriVariables['id'] ?? '');
        $pool = $this->pools->find($poolId);
        if (!$pool instanceof SurprisePool) {
            throw new UnprocessableEntityException('Surprise pool not found.');
        }

        return $this->loyaltyPoints->addPoolItem(
            $pool,
            (string) $data->label,
            (string) $data->rewardType,
            (int) $data->rewardValue,
            (int) ($data->weight ?? 1),
            false !== $data->active,
        );
    }
}
