<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyAccountingReconciliationResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AccountingAgencyManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyAccountingReconciliationResource> */
final class AgencyAccountingReconciliationProvider implements ProviderInterface
{
    public function __construct(
        private AccountingAgencyManager $accounting,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyAccountingReconciliationResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query parameters from and to (YYYY-MM-DD) are required.');
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', $fromRaw);
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', $toRaw);
        if (false === $from || false === $to) {
            throw new UnprocessableEntityException('Invalid date range.');
        }

        $agency = $this->accounting->requireAgency();
        $result = $this->accounting->reconcile($agency, $from, $to);

        return new AgencyAccountingReconciliationResource(
            id: sprintf('%s_%s', $result['from'], $result['to']),
            from: $result['from'],
            to: $result['to'],
            paidPaymentsCount: $result['paidPaymentsCount'],
            paidAmount: $result['paidAmount'],
            journalCreditCount: $result['journalCreditCount'],
            journalCreditAmount: $result['journalCreditAmount'],
            unmatchedPaymentIds: $result['unmatchedPaymentIds'],
            unmatchedJournalSourceIds: $result['unmatchedJournalSourceIds'],
        );
    }
}
