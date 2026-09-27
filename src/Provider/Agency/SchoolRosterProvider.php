<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SchoolRosterResource;
use App\Exception\UnprocessableEntityException;
use App\Manager\SchoolBusManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<SchoolRosterResource> */
final class SchoolRosterProvider implements ProviderInterface
{
    public function __construct(
        private SchoolBusManager $schoolBus,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SchoolRosterResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $contractId = (string) ($request?->query->get('contractId') ?? '');
        $date = (string) ($request?->query->get('date') ?? '');

        if ('' === $contractId) {
            throw new UnprocessableEntityException('Query contractId is required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new UnprocessableEntityException('Query date (YYYY-MM-DD) is required.');
        }

        $data = $this->schoolBus->roster($contractId, $date);

        return new SchoolRosterResource(
            id: sprintf('%s-%s', $data['contractId'], $data['date']),
            contractId: $data['contractId'],
            date: $data['date'],
            embarkationId: $data['embarkationId'],
            students: $data['students'],
            transportId: $data['transportId'] ?? null,
            transportLabel: $data['transportLabel'] ?? null,
            plateNumber: $data['plateNumber'] ?? null,
        );
    }
}
