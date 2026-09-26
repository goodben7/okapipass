<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyFleetSummaryResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AgencyFleetReportManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyFleetSummaryResource> */
final class AgencyFleetSummaryProvider implements ProviderInterface
{
    public function __construct(
        private AgencyFleetReportManager $reports,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyFleetSummaryResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters from and to (YYYY-MM-DD) are required.');
        }
        $result = $this->reports->summary(
            $this->reports->requireAgency(),
            new \DateTimeImmutable($fromRaw),
            new \DateTimeImmutable($toRaw),
        );

        return new AgencyFleetSummaryResource(
            id: sprintf('%s_%s', $result['from'], $result['to']),
            from: $result['from'],
            to: $result['to'],
            availabilityPercent: $result['availabilityPercent'],
            occupancyPercent: $result['occupancyPercent'],
            incidentCount: $result['incidentCount'],
            maintenanceCost: $result['maintenanceCost'],
        );
    }
}
