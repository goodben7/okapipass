<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Agency\AgencyPermission;
use App\Domain\Agency\AgencyQrPayloadBuilder;
use App\Dto\Agency\ValidateAgencyTicketQrDto;
use App\Dto\Agency\ValidateBoardingResultDto;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyTicket;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Manager\TravelerPassManager;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * POST /agency/embarkations/{id}/validate-boarding
 * POST /agency/trips/{id}/validate-boarding
 *
 * @implements ProcessorInterface<ValidateAgencyTicketQrDto, ValidateBoardingResultDto>
 */
final class ValidateBoardingProcessor implements ProcessorInterface
{
    private const int GEOFENCE_RADIUS_METERS = 500;

    public function __construct(
        private AgencyQrPayloadBuilder $qr,
        private AgencyContext $agencyContext,
        private TravelerPassManager $passManager,
        private AgencyTicketRepository $tickets,
        private AgencyEmbarkationRepository $embarkations,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ValidateBoardingResultDto
    {
        \assert($data instanceof ValidateAgencyTicketQrDto);
        $this->agencyContext->requirePermission(AgencyPermission::EMBARKATION_WRITE);

        $id = (string) ($uriVariables['id'] ?? '');
        if ('' === $id && isset($context['previous_data']) && $context['previous_data'] instanceof AgencyEmbarkation) {
            $id = (string) $context['previous_data']->getId();
        }
        $embarkation = $this->embarkations->find($id);
        if (!$embarkation instanceof AgencyEmbarkation) {
            throw new UnavailableDataException('Embarkation not found.');
        }
        $this->agencyContext->assertOwns($embarkation->getAgency());

        $token = $this->qr->resolveScanToken(trim((string) $data->token));
        $preview = $this->tickets->findOneByQrToken($token);
        if (!$preview instanceof AgencyTicket) {
            throw new UnavailableDataException('QR token not found.');
        }
        $this->agencyContext->assertOwns($preview->getAgency());

        $offer = $embarkation->getOffer();
        $ticketOffer = $preview->getOffer();
        if (null === $offer || null === $ticketOffer || $offer->getId() !== $ticketOffer->getId()) {
            throw new UnprocessableEntityException('Ticket offer does not match this trip.');
        }

        $tripDate = $embarkation->getDepartureDate();
        $travelDate = $preview->getTravelDate();
        if (null === $tripDate || null === $travelDate
            || $tripDate->format('Y-m-d') !== $travelDate->format('Y-m-d')
        ) {
            throw new UnprocessableEntityException('Ticket travelDate does not match this trip.');
        }

        $ticket = $this->qr->validateAndConsume($token);
        $this->passManager->consumeTripForTicket($ticket);
        $this->em->flush();

        $geofence = $this->evaluateGeofence($data->lat, $data->lng, $embarkation);
        $boardedCount = $this->tickets->countBoardedForOfferDate($offer, $travelDate);

        return new ValidateBoardingResultDto(
            ticketId: (string) $ticket->getId(),
            status: (string) $ticket->getStatus(),
            boardedCount: $boardedCount,
            seatNumber: $ticket->getSeatNumber(),
            passengerName: $ticket->getPassengerName(),
            reference: $ticket->getReference(),
            embarkationId: $embarkation->getId(),
            geofenceWarning: $geofence['geofenceWarning'],
            warnings: $geofence['warnings'],
        );
    }

    /**
     * Soft geofence: never hard-blocks. Warns when coords provided but no depot reference,
     * or when distance to a known reference exceeds radius.
     *
     * @return array{geofenceWarning: bool, warnings: list<string>}
     */
    private function evaluateGeofence(?float $lat, ?float $lng, AgencyEmbarkation $embarkation): array
    {
        if (null === $lat || null === $lng) {
            return ['geofenceWarning' => false, 'warnings' => []];
        }

        $ref = $this->resolveReferenceCoords($embarkation);
        if (null === $ref) {
            $this->logger->info('agency.boarding.geofence_unchecked', [
                'embarkationId' => $embarkation->getId(),
                'lat' => $lat,
                'lng' => $lng,
            ]);

            return [
                'geofenceWarning' => true,
                'warnings' => ['GEOFENCE_UNCHECKED'],
            ];
        }

        $distance = $this->haversineMeters($lat, $lng, $ref['lat'], $ref['lng']);
        if ($distance > self::GEOFENCE_RADIUS_METERS) {
            $this->logger->info('agency.boarding.geofence_warning', [
                'embarkationId' => $embarkation->getId(),
                'distanceMeters' => (int) round($distance),
            ]);

            return [
                'geofenceWarning' => true,
                'warnings' => ['GEOFENCE_DISTANCE'],
            ];
        }

        return ['geofenceWarning' => false, 'warnings' => []];
    }

    /**
     * MVP: no lat/lng on AgencyTransport / AgencyDepot — always unchecked when coords sent.
     * Hook left for future depot coordinates.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function resolveReferenceCoords(AgencyEmbarkation $embarkation): ?array
    {
        unset($embarkation);

        return null;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }
}
