<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\CreatePosSaleDto;
use App\State\Agency\CreatePosSaleProcessor;

#[ApiResource(
    shortName: 'PosSale',
    operations: [
        new Post(
            uriTemplate: '/agency/pos/sales',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreatePosSaleDto::class,
            output: PosSaleResource::class,
            processor: CreatePosSaleProcessor::class,
            read: false,
            status: 201,
        ),
    ]
)]
final class PosSaleResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $sessionId,
        public string $bookingId,
        public string $ticketId,
        public string $paymentId,
        public int $amount,
    ) {
    }
}
