<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyTransportAvailabilityResource;
use App\Domain\Agency\AgencyTransportAvailabilityService;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyTransportRepository;
use App\Service\Agency\AgencyContext;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyTransportAvailabilityResource> */
final class AgencyTransportAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private AgencyTransportRepository $transports,
        private AgencyContext $agencyContext,
        private AgencyTransportAvailabilityService $availability,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyTransportAvailabilityResource
    {
        $transport = $this->transports->find($uriVariables['transportId'] ?? null);
        if (null === $transport) {
            throw new UnavailableDataException('Transport not found.');
        }
        $this->agencyContext->assertOwns($transport->getAgency());

        $request = $this->requestStack->getCurrentRequest();
        $fromRaw = (string) ($request?->query->get('from') ?? '');
        $toRaw = (string) ($request?->query->get('to') ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw)) {
            throw new UnprocessableEntityException('Query from (YYYY-MM-DD) is required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            throw new UnprocessableEntityException('Query to (YYYY-MM-DD) is required.');
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', $fromRaw);
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', $toRaw);
        if (false === $from || false === $to) {
            throw new UnprocessableEntityException('Invalid from or to date.');
        }

        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        $data = $this->availability->buildCalendar($transport, $from, $to);

        return new AgencyTransportAvailabilityResource(
            transportId: $data['transportId'],
            from: $data['from'],
            to: $data['to'],
            days: $data['days'],
        );
    }
}
