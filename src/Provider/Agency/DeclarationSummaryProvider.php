<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyDeclarationSummaryResource;
use App\Manager\PassDeclarationManager;

/** @implements ProviderInterface<AgencyDeclarationSummaryResource> */
final class DeclarationSummaryProvider implements ProviderInterface
{
    public function __construct(private PassDeclarationManager $manager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyDeclarationSummaryResource
    {
        $summary = $this->manager->summary();

        return new AgencyDeclarationSummaryResource(
            id: 'summary',
            fptDue: $summary['fptDue'],
            currency: $summary['currency'],
            draft: $summary['draft'],
            submitted: $summary['submitted'],
            paid: $summary['paid'],
            byCurrency: $summary['byCurrency'] ?? [],
        );
    }
}
