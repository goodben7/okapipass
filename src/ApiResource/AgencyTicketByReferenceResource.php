<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Entity\AgencyTicket;
use App\State\Agency\AgencyTicketByReferenceProvider;

/**
 * GET /api/agency/tickets/by-reference/{reference}
 *
 * Dedicated resource: AgencyTicket identifier is `id`, not `reference`.
 * A Get on the entity with {reference} skips the provider and yields $data 500.
 */
#[ApiResource(
    shortName: 'AgencyTicketByReference',
    operations: [
        new Get(
            uriTemplate: '/agency/tickets/by-reference/{reference}',
            uriVariables: ['reference'],
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyTicketByReferenceProvider::class,
            output: AgencyTicket::class,
            normalizationContext: ['groups' => ['agency_ticket:get']],
        ),
    ]
)]
final class AgencyTicketByReferenceResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $reference,
    ) {
    }
}
