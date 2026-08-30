<?php

namespace App\Provider\PublicAgency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Public\PublicAgencySeatAvailabilityResource;
use App\Domain\Agency\SeatOccupancyService;
use App\Domain\PublicAgency\PublicAgencyCatalogService;
use App\Exception\UnprocessableEntityException;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<PublicAgencySeatAvailabilityResource> */
final class PublicAgencySeatAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private PublicAgencyCatalogService $catalog,
        private SeatOccupancyService $occupancy,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicAgencySeatAvailabilityResource
    {
        $offer = $this->catalog->requireOnlineOffer((string) ($uriVariables['offerId'] ?? ''));

        $request = $this->requestStack->getCurrentRequest();
        $travelDateRaw = (string) ($request?->query->get('travelDate') ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $travelDateRaw)) {
            throw new UnprocessableEntityException('Query travelDate (YYYY-MM-DD) is required.');
        }

        $travelDate = \DateTimeImmutable::createFromFormat('Y-m-d', $travelDateRaw);
        if (false === $travelDate) {
            throw new UnprocessableEntityException('Invalid travelDate.');
        }

        $travelDate = $travelDate->setTime(0, 0);
        if ($travelDate < new \DateTimeImmutable('today')) {
            throw new UnprocessableEntityException('travelDate must be today or in the future.');
        }

        $data = $this->occupancy->availability($offer, $travelDate);

        return new PublicAgencySeatAvailabilityResource(
            offerId: $data['offerId'],
            travelDate: $data['travelDate'],
            capacity: $data['capacity'],
            availableCount: $data['availableCount'],
            isFull: $data['isFull'],
            layout: $data['layout'],
            occupiedSeats: $data['occupiedSeats'],
        );
    }
}
