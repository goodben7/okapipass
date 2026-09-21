<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\RejectOntFptDeclarationDto;
use App\Dto\Agency\SubmitOntFptDeclarationDto;
use App\Entity\PassDeclaration;
use App\Provider\Ont\OntFptDeclarationItemProvider;
use App\State\Agency\PayOntFptDeclarationProcessor;
use App\State\Agency\RejectOntFptDeclarationProcessor;
use App\State\Agency\SubmitOntFptDeclarationProcessor;
use App\State\Agency\ValidateOntFptDeclarationProcessor;

/**
 * ONT transversal FPT endpoints (spec §6.10).
 *
 * Workflow: draft → submitted → validated → paid
 *                          ↘ rejected (agence peut resoumettre)
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
        new Post(
            uriTemplate: '/ont/fpt-declarations/{id}/validate',
            uriVariables: ['id'],
            security: 'is_granted("ROLE_ONT_ADMIN") or is_granted("ROLE_SUPER_ADMIN")',
            input: false,
            deserialize: false,
            output: PassDeclaration::class,
            provider: OntFptDeclarationItemProvider::class,
            processor: ValidateOntFptDeclarationProcessor::class,
            status: 200,
            normalizationContext: ['groups' => ['pass_declaration:get']],
        ),
        new Post(
            uriTemplate: '/ont/fpt-declarations/{id}/reject',
            uriVariables: ['id'],
            security: 'is_granted("ROLE_ONT_ADMIN") or is_granted("ROLE_SUPER_ADMIN")',
            input: RejectOntFptDeclarationDto::class,
            output: PassDeclaration::class,
            provider: OntFptDeclarationItemProvider::class,
            processor: RejectOntFptDeclarationProcessor::class,
            status: 200,
            normalizationContext: ['groups' => ['pass_declaration:get']],
        ),
        new Post(
            uriTemplate: '/ont/fpt-declarations/{id}/pay',
            uriVariables: ['id'],
            security: 'is_granted("ROLE_ONT_ADMIN") or is_granted("ROLE_SUPER_ADMIN")',
            input: false,
            deserialize: false,
            output: PassDeclaration::class,
            provider: OntFptDeclarationItemProvider::class,
            processor: PayOntFptDeclarationProcessor::class,
            status: 200,
            normalizationContext: ['groups' => ['pass_declaration:get']],
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
