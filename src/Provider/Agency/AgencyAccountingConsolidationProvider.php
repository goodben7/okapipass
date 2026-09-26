<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyAccountingConsolidationResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AccountingAgencyManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyAccountingConsolidationResource> */
final class AgencyAccountingConsolidationProvider implements ProviderInterface
{
    public function __construct(
        private AccountingAgencyManager $accounting,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyAccountingConsolidationResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters from and to (YYYY-MM-DD) are required.');
        }
        $result = $this->accounting->consolidation(
            $this->accounting->requireAgency(),
            new \DateTimeImmutable($fromRaw),
            new \DateTimeImmutable($toRaw),
        );

        return new AgencyAccountingConsolidationResource(
            id: sprintf('%s_%s', $result['from'], $result['to']),
            from: $result['from'],
            to: $result['to'],
            depots: $result['depots'],
        );
    }
}
