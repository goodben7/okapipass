<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyDeclarationSummaryResource;
use App\Manager\PassDeclarationManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyDeclarationSummaryResource> */
final class DeclarationSummaryProvider implements ProviderInterface
{
    public function __construct(
        private PassDeclarationManager $manager,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyDeclarationSummaryResource
    {
        $raw = $this->requestStack->getCurrentRequest()?->query->get('agencyId');
        $agencyId = \is_string($raw) ? $raw : null;

        $summary = $this->manager->summary($agencyId);

        return new AgencyDeclarationSummaryResource(
            id: 'summary',
            fptDue: $summary['fptDue'],
            currency: $summary['currency'],
            draft: $summary['draft'],
            submitted: $summary['submitted'],
            validated: $summary['validated'] ?? 0,
            paid: $summary['paid'],
            byCurrency: $summary['byCurrency'] ?? [],
        );
    }
}
