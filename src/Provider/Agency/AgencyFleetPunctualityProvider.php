<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyFleetPunctualityResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AgencyFleetReportManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyFleetPunctualityResource> */
final class AgencyFleetPunctualityProvider implements ProviderInterface
{
    public function __construct(
        private AgencyFleetReportManager $reports,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyFleetPunctualityResource
    {
        [$from, $to] = $this->parseRange();
        $result = $this->reports->punctuality($this->reports->requireAgency(), $from, $to);

        return new AgencyFleetPunctualityResource(
            id: sprintf('punctuality_%s_%s', $result['from'], $result['to']),
            from: $result['from'],
            to: $result['to'],
            rows: $result['rows'],
        );
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function parseRange(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters from and to (YYYY-MM-DD) are required.');
        }

        return [new \DateTimeImmutable($fromRaw), new \DateTimeImmutable($toRaw)];
    }
}
