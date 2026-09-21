<?php

namespace App\Provider\Ont;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\OntDashboardResource;
use App\Domain\Ont\OntDashboardService;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<OntDashboardResource> */
final class OntDashboardProvider implements ProviderInterface
{
    public function __construct(
        private OntDashboardService $dashboard,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OntDashboardResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $periodMonth = $request?->query->get('periodMonth');
        $periodMonth = \is_string($periodMonth) ? $periodMonth : null;

        $data = $this->dashboard->build($periodMonth);

        return new OntDashboardResource(
            id: 'ont-dashboard',
            generatedAt: $data['generatedAt'],
            periodMonth: $data['periodMonth'],
            kpis: $data['kpis'],
            fptByMonth: $data['fptByMonth'],
            recentDeclarations: $data['recentDeclarations'],
            topAgenciesByFptDue: $data['topAgenciesByFptDue'],
            alerts: $data['alerts'],
            pollSuggestedSeconds: 15,
        );
    }
}
