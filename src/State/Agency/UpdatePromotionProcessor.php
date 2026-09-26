<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdatePromotionDto;
use App\Entity\Promotion;
use App\Manager\LoyaltyManager;

/** @implements ProcessorInterface<UpdatePromotionDto, Promotion> */
final class UpdatePromotionProcessor implements ProcessorInterface
{
    public function __construct(private LoyaltyManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Promotion
    {
        \assert($data instanceof UpdatePromotionDto);
        $promotion = $context['previous_data'] ?? null;
        \assert($promotion instanceof Promotion);

        return $this->manager->updatePromotion($promotion, $data);
    }
}
