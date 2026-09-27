<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyFleetTripsResource;
use App\Domain\Agency\AgencyPermission;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyEmbarkationRepository;
use App\Service\Agency\AgencyContext;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<AgencyFleetTripsResource> */
final class AgencyFleetTripsProvider implements ProviderInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AgencyEmbarkationRepository $embarkations,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyFleetTripsResource
    {
        $this->agencyContext->requirePermission(AgencyPermission::FLEET_READ);
        $agency = $this->agencyContext->requireAgency();

        $request = $this->requestStack->getCurrentRequest();
        $dateRaw = (string) ($request?->query->get('date') ?? '');
        $date = null;
        if ('' !== $dateRaw) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
                throw new UnprocessableEntityException('Query date (YYYY-MM-DD) is invalid.');
            }
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
            if (false === $parsed) {
                throw new UnprocessableEntityException('Invalid date.');
            }
            $date = $parsed->setTime(0, 0);
        }

        $serviceType = $request?->query->get('serviceType');
        $serviceType = \is_string($serviceType) && '' !== $serviceType ? $serviceType : null;

        $unassignedRaw = $request?->query->get('unassignedOnly');
        $unassignedOnly = \in_array((string) $unassignedRaw, ['1', 'true', 'yes'], true);

        $rows = $this->embarkations->findFleetTrips($agency, $date, $serviceType, $unassignedOnly);
        $trips = [];
        foreach ($rows as $e) {
            $transport = $e->getTransport();
            $driver = $e->getDriver();
            $offer = $e->getOffer();
            $trips[] = [
                'id' => $e->getId(),
                'tripId' => $e->getId(),
                'embarkationId' => $e->getId(),
                'label' => $e->getLabel(),
                'status' => $e->getStatus(),
                'departureDate' => $e->getDepartureDate()?->format('Y-m-d'),
                'departureTime' => $e->getDepartureTime(),
                'serviceType' => $offer?->getServiceType(),
                'offerId' => $offer?->getId(),
                'transportId' => $transport?->getId(),
                'transportLabel' => $transport?->getLabel(),
                'plateNumber' => $transport?->getPlateNumber(),
                'driverId' => $driver?->getId(),
                'driverName' => $driver?->getFullName(),
                'unassigned' => null === $transport,
            ];
        }

        return new AgencyFleetTripsResource(
            id: 'fleet-trips',
            trips: $trips,
        );
    }
}
