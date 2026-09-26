<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PromotionValidateResource;
use App\Dto\Agency\ValidatePromotionDto;
use App\Manager\LoyaltyManager;

/** @implements ProcessorInterface<ValidatePromotionDto, PromotionValidateResource> */
final class ValidatePromotionProcessor implements ProcessorInterface
{
    public function __construct(private LoyaltyManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PromotionValidateResource
    {
        \assert($data instanceof ValidatePromotionDto);

        $result = $this->manager->validatePromotion($data);

        return new PromotionValidateResource(
            id: $result['promotionId'] ?? $result['loyaltyRuleId'] ?? 'none',
            discountAmount: $result['discountAmount'],
            promoCode: $result['promoCode'],
            promotionId: $result['promotionId'],
            loyaltyRuleId: $result['loyaltyRuleId'],
            finalTicketPrice: $result['finalTicketPrice'],
        );
    }
}
