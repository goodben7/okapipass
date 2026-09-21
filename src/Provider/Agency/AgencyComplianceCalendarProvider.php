<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyComplianceCalendarResource;
use App\Manager\AgencyObligationManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyComplianceCalendarResource> */
final class AgencyComplianceCalendarProvider implements ProviderInterface
{
    public function __construct(
        private AgencyObligationManager $manager,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyComplianceCalendarResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $from = $request?->query->get('from');
        $to = $request?->query->get('to');
        $from = \is_string($from) ? $from : null;
        $to = \is_string($to) ? $to : null;

        $data = $this->manager->calendar($from, $to);

        return new AgencyComplianceCalendarResource(
            id: 'compliance-calendar',
            from: $data['from'],
            to: $data['to'],
            kpis: $data['kpis'],
            events: $data['events'],
        );
    }
}
