<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyAccountingMarginResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AccountingAgencyManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyAccountingMarginResource> */
final class AgencyAccountingMarginProvider implements ProviderInterface
{
    public function __construct(
        private AccountingAgencyManager $accounting,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyAccountingMarginResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters from and to (YYYY-MM-DD) are required.');
        }
        $from = new \DateTimeImmutable($fromRaw);
        $to = new \DateTimeImmutable($toRaw);
        $result = $this->accounting->marginReport($this->accounting->requireAgency(), $from, $to);

        return new AgencyAccountingMarginResource(
            id: sprintf('%s_%s', $result['from'], $result['to']),
            from: $result['from'],
            to: $result['to'],
            ca: $result['ca'],
            passOnt: $result['passOnt'],
            commission: $result['commission'],
            net: $result['net'],
            currency: $result['currency'],
        );
    }
}
