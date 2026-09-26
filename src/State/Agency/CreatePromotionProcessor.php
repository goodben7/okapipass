<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreatePromotionDto;
use App\Entity\Promotion;
use App\Manager\LoyaltyManager;

/** @implements ProcessorInterface<CreatePromotionDto, Promotion> */
final class CreatePromotionProcessor implements ProcessorInterface
{
    public function __construct(private LoyaltyManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Promotion
    {
        \assert($data instanceof CreatePromotionDto);

        return $this->manager->createPromotion($data);
    }
}
