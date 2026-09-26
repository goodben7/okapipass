<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyPosKpiResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\AccountingAgencyManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyPosKpiResource> */
final class AgencyPosKpiProvider implements ProviderInterface
{
    public function __construct(
        private AccountingAgencyManager $accounting,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyPosKpiResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $dateRaw = (string) ($request?->query->get('date') ?? (new \DateTimeImmutable())->format('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
            throw new UnprocessableEntityException('Query parameter date (YYYY-MM-DD) is required.');
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
        if (false === $date) {
            throw new UnprocessableEntityException('Invalid date.');
        }

        $result = $this->accounting->posKpi($this->accounting->requireAgency(), $date);

        return new AgencyPosKpiResource(
            id: $result['date'],
            date: $result['date'],
            ticketsCount: $result['ticketsCount'],
            salesCount: $result['salesCount'],
            caTotal: $result['caTotal'],
            mix: $result['mix'],
            bySeller: $result['bySeller'],
            byPointOfSale: $result['byPointOfSale'],
        );
    }
}
