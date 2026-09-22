<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Agency\AgencyTicketPrintProvider;

#[ApiResource(
    shortName: 'AgencyTicketPrint',
    operations: [
        new Get(
            uriTemplate: '/agency/tickets/{id}/print',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyTicketPrintProvider::class,
        ),
    ]
)]
class AgencyTicketPrintResource
{
    /**
     * @param array<string, mixed> $ticket
     * @param array<string, mixed> $offer
     * @param array<string, mixed> $agency
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public array $ticket,
        public array $offer,
        public array $agency,
        public string $qrPayload,
    ) {
    }
}
