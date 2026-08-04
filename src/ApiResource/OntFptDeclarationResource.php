<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\SubmitOntFptDeclarationDto;
use App\Entity\PassDeclaration;
use App\State\Agency\PayOntFptDeclarationProcessor;
use App\State\Agency\SubmitOntFptDeclarationProcessor;

/**
 * ONT transversal FPT endpoints (spec §6.10).
 */
#[ApiResource(
    shortName: 'OntFptDeclaration',
    output: PassDeclaration::class,
    operations: [
        new Post(
            uriTemplate: '/ont/fpt-declarations',
            security: 'is_granted("ROLE_PARTNER")',
            input: SubmitOntFptDeclarationDto::class,
            output: PassDeclaration::class,
            processor: SubmitOntFptDeclarationProcessor::class,
            status: 200,
        ),
        new Patch(
            uriTemplate: '/ont/fpt-declarations/{id}/pay',
            security: 'is_granted("ROLE_ONT_ADMIN") or is_granted("ROLE_SUPER_ADMIN")',
            input: false,
            deserialize: false,
            output: PassDeclaration::class,
            processor: PayOntFptDeclarationProcessor::class,
            read: false,
        ),
    ]
)]
class OntFptDeclarationResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
    ) {
    }
}
