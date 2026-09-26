<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyFillForecastResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AgencyAnalyticsManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyFillForecastResource> */
final class AgencyFillForecastProvider implements ProviderInterface
{
    public function __construct(
        private AgencyAnalyticsManager $analytics,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyFillForecastResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $offer = (string) ($request?->query->get('offer') ?? '');
        $dateRaw = (string) ($request?->query->get('date') ?? '');
        if ('' === $offer || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
            throw new UnprocessableEntityException('Query parameters offer and date are required.');
        }
        if (str_contains($offer, '/')) {
            $parts = explode('/', rtrim($offer, '/'));
            $offer = (string) end($parts);
        }

        $result = $this->analytics->fillForecast(
            $this->analytics->requireAgency(),
            $offer,
            new \DateTimeImmutable($dateRaw),
        );

        return new AgencyFillForecastResource(
            id: $result['offerId'].'_'.$result['date'],
            offerId: $result['offerId'],
            date: $result['date'],
            ticketsSold: $result['ticketsSold'],
            capacity: $result['capacity'],
            occupancyPercent: $result['occupancyPercent'],
            projectedFillPercent: $result['projectedFillPercent'],
        );
    }
}
