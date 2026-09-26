<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyDriverMissionsResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AgencyFleetReportManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyDriverMissionsResource> */
final class AgencyDriverMissionsProvider implements ProviderInterface
{
    public function __construct(
        private AgencyFleetReportManager $reports,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyDriverMissionsResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $dateRaw = (string) ($request?->query->get('date') ?? (new \DateTimeImmutable())->format('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
            throw new UnprocessableEntityException('Query parameter date (YYYY-MM-DD) is required.');
        }
        $driver = $request?->query->get('driver');
        $driver = null !== $driver ? (string) $driver : null;
        $agency = $this->reports->requireAgency();
        $missions = $this->reports->driverMissions($agency, new \DateTimeImmutable($dateRaw), $driver);

        return new AgencyDriverMissionsResource(
            id: sprintf('%s_%s', $dateRaw, $driver ?: 'all'),
            date: $dateRaw,
            driver: $driver,
            missions: $missions,
        );
    }
}
