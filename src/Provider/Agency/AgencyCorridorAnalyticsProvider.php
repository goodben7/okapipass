<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyCorridorAnalyticsResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AgencyAnalyticsManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyCorridorAnalyticsResource> */
final class AgencyCorridorAnalyticsProvider implements ProviderInterface
{
    public function __construct(
        private AgencyAnalyticsManager $analytics,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyCorridorAnalyticsResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters from and to are required (YYYY-MM-DD).');
        }

        $result = $this->analytics->corridors(
            $this->analytics->requireAgency(),
            new \DateTimeImmutable($fromRaw),
            new \DateTimeImmutable($toRaw),
        );

        return new AgencyCorridorAnalyticsResource(
            id: $result['from'].'_'.$result['to'],
            from: $result['from'],
            to: $result['to'],
            corridors: $result['corridors'],
        );
    }
}
