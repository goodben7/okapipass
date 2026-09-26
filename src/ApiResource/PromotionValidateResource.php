<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\ValidatePromotionDto;
use App\State\Agency\ValidatePromotionProcessor;

#[ApiResource(
    shortName: 'PromotionValidate',
    operations: [
        new Post(
            uriTemplate: '/agency/promotions/validate',
            security: AgencyPortalAccess::EXPRESSION,
            input: ValidatePromotionDto::class,
            output: PromotionValidateResource::class,
            processor: ValidatePromotionProcessor::class,
            read: false,
            status: 200,
        ),
    ]
)]
final class PromotionValidateResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public int $discountAmount = 0,
        public ?string $promoCode = null,
        public ?string $promotionId = null,
        public ?string $loyaltyRuleId = null,
        public int $finalTicketPrice = 0,
    ) {
    }
}
