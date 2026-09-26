<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyFleetCostPerKmResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AgencyFleetReportManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyFleetCostPerKmResource> */
final class AgencyFleetCostPerKmProvider implements ProviderInterface
{
    public function __construct(
        private AgencyFleetReportManager $reports,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyFleetCostPerKmResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $transport = (string) ($request?->query->get('transport') ?? '');
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');
        if ('' === $transport || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters transport, from and to are required.');
        }
        if (str_contains($transport, '/')) {
            $parts = explode('/', rtrim($transport, '/'));
            $transport = (string) end($parts);
        }
        $result = $this->reports->costPerKm(
            $this->reports->requireAgency(),
            $transport,
            new \DateTimeImmutable($fromRaw),
            new \DateTimeImmutable($toRaw),
        );

        return new AgencyFleetCostPerKmResource(
            id: sprintf('%s_%s_%s', $result['transportId'], $result['from'], $result['to']),
            transportId: $result['transportId'],
            from: $result['from'],
            to: $result['to'],
            fuelCost: $result['fuelCost'],
            maintenanceCost: $result['maintenanceCost'],
            deltaOdometer: $result['deltaOdometer'],
            costPerKm: $result['costPerKm'],
            litersPer100Km: (float) ($result['litersPer100Km'] ?? 0.0),
        );
    }
}
