<?php

namespace App\ApiResource;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\ValidateAgencyTicketQrDto;
use App\Entity\AgencyTicket;
use App\State\Agency\ValidateAgencyTicketQrProcessor;

#[ApiResource(
    shortName: 'AgencyTicketQrValidation',
    normalizationContext: ['groups' => ['agency_ticket:get']],
    operations: [
        new Post(
            uriTemplate: '/agency/tickets/validate-qr',
            security: AgencyPortalAccess::EXPRESSION,
            input: ValidateAgencyTicketQrDto::class,
            output: AgencyTicket::class,
            processor: ValidateAgencyTicketQrProcessor::class,
            read: false,
            status: 200,
        ),
    ]
)]
final class AgencyTicketQrValidationResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id = 'validate-qr',
    ) {
    }
}
