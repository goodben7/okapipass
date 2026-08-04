<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencySeatAvailabilityResource;
use App\Domain\Agency\SeatOccupancyService;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyOfferRepository;
use App\Service\Agency\AgencyContext;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencySeatAvailabilityResource> */
final class SeatAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private AgencyOfferRepository $offers,
        private AgencyContext $agencyContext,
        private SeatOccupancyService $occupancy,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencySeatAvailabilityResource
    {
        $offer = $this->offers->find($uriVariables['offerId'] ?? null);
        if (null === $offer) {
            throw new UnavailableDataException('Offer not found.');
        }
        $this->agencyContext->assertOwns($offer->getAgency());

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

        $excludeBookingId = $request?->query->get('excludeBookingId');
        $excludeBookingId = \is_string($excludeBookingId) && '' !== $excludeBookingId ? $excludeBookingId : null;

        $data = $this->occupancy->availability($offer, $travelDate, $excludeBookingId);

        return new AgencySeatAvailabilityResource(
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
