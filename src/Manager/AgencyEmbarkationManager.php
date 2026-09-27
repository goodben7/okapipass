<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Domain\Agency\AgencyPricingService;
use App\Domain\Agency\AgencyTransportAvailabilityService;
use App\Domain\Agency\SeatLayoutBuilder;
use App\Domain\Agency\SeatOccupancyService;
use App\Dto\Agency\AddEmbarkationTicketsDto;
use App\Dto\Agency\AssignTripTransportDto;
use App\Dto\Agency\CreateAgencyEmbarkationDto;
use App\Dto\Agency\TripAssignResultDto;
use App\Dto\Agency\UnassignTripTransportDto;
use App\Entity\AgencyBooking;
use App\Entity\AgencyDriver;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTransport;
use App\Entity\AgencyTripAssignment;
use App\Entity\AgencyWebhookSubscription;
use App\Entity\DeclarationLine;
use App\Entity\PassDeclaration;
use App\Entity\User;
use App\Exception\ConflictException;
use App\Exception\UnauthorizedActionException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyTicketRepository;
use App\Repository\AgencyTransportRepository;
use App\Repository\AgencyTripAssignmentRepository;
use App\Service\Agency\AgencyContext;
use App\Service\Agency\AgencyWebhookDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AgencyEmbarkationManager
{
    private const int DEFAULT_DURATION_MINUTES = 240;

    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyOfferRepository $offers,
        private AgencyTransportRepository $transports,
        private AgencyTicketRepository $tickets,
        private AgencyBookingRepository $bookings,
        private AgencyPricingService $pricing,
        private AgencyDriverManager $drivers,
        private AgencyEmbarkationRepository $embarkations,
        private AgencyTripAssignmentRepository $assignments,
        private AgencyTransportAvailabilityService $transportAvailability,
        private SeatOccupancyService $occupancy,
        private SeatLayoutBuilder $seatLayoutBuilder,
        private AgencyAuditLogManager $auditLog,
        private AgencyWebhookDispatcher $webhooks,
        private LoggerInterface $logger,
    ) {
    }

    public function create(CreateAgencyEmbarkationDto $dto): AgencyEmbarkation
    {
        $agency = $this->agencyContext->requireAgency();
        $offer = $this->resolveOffer((string) $dto->offer, $agency->getId());
        $transport = null;
        if (null !== $dto->transport && '' !== trim($dto->transport)) {
            $transport = $this->resolveTransport((string) $dto->transport, $agency->getId());
        }

        $embarkation = new AgencyEmbarkation();
        $embarkation->setAgency($agency);
        $embarkation->setLabel((string) $dto->label);
        $embarkation->setOffer($offer);
        $embarkation->setTransport($transport);
        $embarkation->setDepartureDate($this->parseDate((string) $dto->departureDate));
        $embarkation->setDepartureTime((string) $dto->departureTime);
        $embarkation->setNotes($dto->notes);
        $embarkation->setDriver($this->drivers->resolveForAssignment($dto->driver, $agency->getId()));
        $embarkation->setStatus(AgencyEmbarkation::STATUS_PLANNED);

        $this->em->persist($embarkation);
        $this->em->flush();

        if (null !== $transport) {
            $this->openAssignment($embarkation, $transport, $embarkation->getDriver(), null);
            $this->em->flush();
        }

        if (!empty($dto->ticketIds)) {
            $this->addTickets($embarkation, new AddEmbarkationTicketsDto($dto->ticketIds));
        }

        return $embarkation;
    }

    /**
     * Assign or reassign a transport (+ optional driver) to a course (embarkation).
     */
    public function assign(AgencyEmbarkation $embarkation, AssignTripTransportDto $dto): TripAssignResultDto
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());
        $this->assertCanAssign($embarkation);

        $status = $embarkation->getStatus();
        if (\in_array($status, [
            AgencyEmbarkation::STATUS_DEPARTED,
            AgencyEmbarkation::STATUS_DECLARED,
            AgencyEmbarkation::STATUS_CLOSED,
        ], true)) {
            throw new UnprocessableEntityException('TRIP_NOT_ASSIGNABLE: Course status does not allow transport assignment.');
        }

        $agency = $embarkation->getAgency();
        $transport = $this->resolveTransport((string) $dto->transportId, $agency?->getId());
        $force = (bool) $dto->force;
        $date = $embarkation->getDepartureDate();
        if (null === $date) {
            throw new UnprocessableEntityException('Embarkation has no departureDate.');
        }

        if (!$force) {
            if (!$transport->isActiveForSale()) {
                throw new ConflictException('TRANSPORT_NOT_AVAILABLE: Transport is not ACTIVE.');
            }
            try {
                $this->transportAvailability->assertAvailableForTravelDate($transport, $date);
            } catch (UnprocessableEntityException $e) {
                throw new ConflictException('TRANSPORT_NOT_AVAILABLE: '.$e->getMessage());
            }
        }

        $offer = $embarkation->getOffer();
        $sold = 0;
        if ($offer instanceof AgencyOffer) {
            $sold = $this->occupancy->soldCount($offer, $date);
            if ($sold > (int) $transport->getCapacity()) {
                throw new ConflictException(sprintf(
                    'CAPACITY_BELOW_SOLD: Transport capacity %d is below sold seats count %d.',
                    (int) $transport->getCapacity(),
                    $sold,
                ));
            }
        }

        $previousTransport = $embarkation->getTransport()
            ?? ($offer instanceof AgencyOffer ? $offer->getTransport() : null);
        $remappedSeats = [];
        if ($offer instanceof AgencyOffer && $sold > 0) {
            $remappedSeats = $this->resolveSeatRemap(
                $offer,
                $date,
                $previousTransport,
                $transport,
                $force,
            );
        }

        $this->assertNoTransportScheduleConflict($embarkation, $transport, $date);

        $driver = $this->drivers->resolveForAssignment($dto->driverId, $agency?->getId());
        if ($driver instanceof AgencyDriver) {
            $this->assertNoDriverScheduleConflict($embarkation, $driver, $date);
        }

        $previous = $this->assignments->findOpenForEmbarkation($embarkation);
        if ($previous instanceof AgencyTripAssignment) {
            $previous->setUnassignedAt(new \DateTimeImmutable('now'));
            if (null !== $dto->reason && '' !== trim($dto->reason)) {
                $prevReason = $previous->getReason();
                $previous->setReason(
                    null !== $prevReason && '' !== $prevReason
                        ? $prevReason.' | reassign: '.$dto->reason
                        : 'reassign: '.$dto->reason
                );
            }
        }

        $embarkation->setTransport($transport);
        $embarkation->setDriver($driver);

        $assignment = $this->openAssignment($embarkation, $transport, $driver, $dto->reason);
        $this->em->flush();

        if ([] !== $remappedSeats && null !== $agency) {
            $user = $this->agencyContext->getUser();
            $this->auditLog->log(
                $agency,
                $user instanceof User ? $user : null,
                'SEAT_REMAP',
                'AgencyEmbarkation',
                (string) $embarkation->getId(),
                ['remappedSeats' => $remappedSeats, 'transportId' => $transport->getId()],
            );
        }

        $warnings = $this->collectWarnings($driver);

        try {
            $this->webhooks->dispatch($agency, AgencyWebhookSubscription::EVENT_TRIP_TRANSPORT_ASSIGNED, [
                'tripId' => $embarkation->getId(),
                'embarkationId' => $embarkation->getId(),
                'transportId' => $transport->getId(),
                'driverId' => $driver?->getId(),
                'assignmentId' => $assignment->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('agency.webhook.trip_assigned_failed', [
                'embarkationId' => $embarkation->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $this->toAssignResult($embarkation, $assignment, $warnings, $remappedSeats);
    }

    public function reassign(AgencyEmbarkation $embarkation, AssignTripTransportDto $dto): TripAssignResultDto
    {
        return $this->assign($embarkation, $dto);
    }

    public function unassign(AgencyEmbarkation $embarkation, UnassignTripTransportDto $dto): TripAssignResultDto
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());
        $this->assertCanAssign($embarkation);

        $status = $embarkation->getStatus();
        if (!\in_array($status, [AgencyEmbarkation::STATUS_PLANNED, AgencyEmbarkation::STATUS_BOARDING], true)) {
            throw new UnprocessableEntityException('TRIP_NOT_ASSIGNABLE: Unassign only allowed for PLANNED/BOARDING courses.');
        }

        $date = $embarkation->getDepartureDate();
        $offer = $embarkation->getOffer();
        $sold = 0;
        if ($offer instanceof AgencyOffer && null !== $date) {
            $sold = $this->occupancy->soldCount($offer, $date);
        }

        if ($sold > 0 && !$dto->force) {
            throw new ConflictException('CAPACITY_BELOW_SOLD: Cannot unassign transport while seats are sold (use force=true).');
        }

        $previous = $this->assignments->findOpenForEmbarkation($embarkation);
        if ($previous instanceof AgencyTripAssignment) {
            $previous->setUnassignedAt(new \DateTimeImmutable('now'));
            if (null !== $dto->reason && '' !== trim($dto->reason)) {
                $previous->setReason($dto->reason);
            }
        }

        // Unassign clears transport (nullable) and driver
        $embarkation->setTransport(null);
        $embarkation->setDriver(null);
        $this->em->flush();

        try {
            $agency = $embarkation->getAgency();
            if (null !== $agency) {
                $this->webhooks->dispatch($agency, AgencyWebhookSubscription::EVENT_TRIP_TRANSPORT_UNASSIGNED, [
                    'tripId' => $embarkation->getId(),
                    'embarkationId' => $embarkation->getId(),
                    'reason' => $dto->reason,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('agency.webhook.trip_unassigned_failed', [
                'embarkationId' => $embarkation->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $this->toAssignResult($embarkation, null, [], []);
    }

    public function addTickets(AgencyEmbarkation $embarkation, AddEmbarkationTicketsDto $dto): AgencyEmbarkation
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());
        if (\in_array($embarkation->getStatus(), [AgencyEmbarkation::STATUS_DECLARED, AgencyEmbarkation::STATUS_CLOSED], true)) {
            throw new UnprocessableEntityException('Cannot modify tickets on a declared/closed embarkation.');
        }

        foreach ($dto->ticketIds ?? [] as $ticketId) {
            $ticket = $this->resolveTicket((string) $ticketId, $embarkation->getAgency()->getId());
            if ($ticket->isCancelled()) {
                throw new UnprocessableEntityException(sprintf('Ticket %s is cancelled.', $ticket->getId()));
            }
            if (null !== $ticket->getEmbarkation() && $ticket->getEmbarkation()->getId() !== $embarkation->getId()) {
                throw new ConflictException(sprintf('Ticket %s already belongs to another embarkation.', $ticket->getId()));
            }
            $ticket->setEmbarkation($embarkation);
            if (AgencyTicket::STATUS_ISSUED === $ticket->getStatus()) {
                $ticket->setStatus(AgencyTicket::STATUS_BOARDED);
            }
        }

        if (AgencyEmbarkation::STATUS_PLANNED === $embarkation->getStatus()) {
            $embarkation->setStatus(AgencyEmbarkation::STATUS_BOARDING);
        }

        $this->em->flush();

        return $embarkation;
    }

    public function removeTicket(AgencyEmbarkation $embarkation, string $ticketId): void
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());
        if (\in_array($embarkation->getStatus(), [AgencyEmbarkation::STATUS_DECLARED, AgencyEmbarkation::STATUS_CLOSED], true)) {
            throw new UnprocessableEntityException('Cannot modify tickets on a declared/closed embarkation.');
        }

        $ticket = $this->resolveTicket($ticketId, $embarkation->getAgency()->getId());
        if ($ticket->getEmbarkation()?->getId() !== $embarkation->getId()) {
            throw new UnavailableDataException('Ticket not found on this embarkation.');
        }

        $ticket->setEmbarkation(null);
        if (AgencyTicket::STATUS_BOARDED === $ticket->getStatus()) {
            $ticket->setStatus(AgencyTicket::STATUS_ISSUED);
        }
        $this->em->flush();
    }

    public function updateStatus(AgencyEmbarkation $embarkation, string $status): AgencyEmbarkation
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());
        $current = $embarkation->getStatus();

        if (AgencyEmbarkation::STATUS_BOARDING === $status) {
            if (!\in_array($current, [AgencyEmbarkation::STATUS_PLANNED, AgencyEmbarkation::STATUS_BOARDING], true)) {
                throw new UnprocessableEntityException('Invalid status transition to BOARDING.');
            }
            $embarkation->setStatus(AgencyEmbarkation::STATUS_BOARDING);
        } elseif (AgencyEmbarkation::STATUS_DEPARTED === $status) {
            if (!\in_array($current, [AgencyEmbarkation::STATUS_PLANNED, AgencyEmbarkation::STATUS_BOARDING], true)) {
                throw new UnprocessableEntityException('Invalid status transition to DEPARTED.');
            }
            $embarkation->setStatus(AgencyEmbarkation::STATUS_DEPARTED);
            $embarkation->setDepartedAt(new \DateTimeImmutable('now'));
        } elseif (AgencyEmbarkation::STATUS_CLOSED === $status) {
            if (AgencyEmbarkation::STATUS_DECLARED !== $current) {
                throw new UnprocessableEntityException('Only DECLARED embarkations can be CLOSED.');
            }
            $embarkation->setStatus(AgencyEmbarkation::STATUS_CLOSED);
        } else {
            throw new UnprocessableEntityException(sprintf('Unsupported status %s.', $status));
        }

        $this->em->flush();

        return $embarkation;
    }

    public function declare(AgencyEmbarkation $embarkation): PassDeclaration
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());

        if (null !== $embarkation->getDeclaration()) {
            // Idempotent: return existing (AC-04 friendly)
            return $embarkation->getDeclaration();
        }

        if (AgencyEmbarkation::STATUS_CLOSED === $embarkation->getStatus()) {
            throw new UnprocessableEntityException('Cannot declare a closed embarkation.');
        }

        $declaration = new PassDeclaration();
        $declaration->setAgency($embarkation->getAgency());
        $declaration->setLabel(sprintf('FPT %s', $embarkation->getLabel()));
        $declaration->setSource(PassDeclaration::SOURCE_EMBARKATION);
        $declaration->setStatus(PassDeclaration::STATUS_SUBMITTED);
        $declaration->setSubmittedAt(new \DateTimeImmutable('now'));
        $declaration->setCurrency($embarkation->getAgency()->getDefaultCurrency());

        foreach ($embarkation->getTickets() as $ticket) {
            if ($ticket->isCancelled()) {
                continue;
            }
            $line = $this->lineFromTicket($ticket);
            $declaration->addLine($line);
            $ticket->setDeclaration($declaration);
            if (AgencyTicket::STATUS_ISSUED === $ticket->getStatus()) {
                $ticket->setStatus(AgencyTicket::STATUS_BOARDED);
            }
        }

        $declaration->recalculateFptTotal();
        $embarkation->setDeclaration($declaration);
        $embarkation->setStatus(AgencyEmbarkation::STATUS_DECLARED);
        $embarkation->setDeclaredAt(new \DateTimeImmutable('now'));
        $declaration->setEmbarkation($embarkation);

        $this->em->persist($declaration);
        $this->em->flush();

        return $declaration;
    }

    private function assertCanAssign(AgencyEmbarkation $embarkation): void
    {
        if ($this->agencyContext->isElevated()
            || \in_array('ROLE_SUPER_ADMIN', $this->agencyContext->getUser()->getRoles(), true)
        ) {
            return;
        }

        $perms = $this->agencyContext->defaultPermissions();
        if (\in_array(AgencyPermission::FLEET_WRITE, $perms, true)) {
            return;
        }

        $offer = $embarkation->getOffer();
        if ($offer instanceof AgencyOffer && $offer->isSchool()
            && \in_array(AgencyPermission::SCHOOL_WRITE, $perms, true)
        ) {
            return;
        }

        throw new UnauthorizedActionException(sprintf('Missing permission "%s".', AgencyPermission::FLEET_WRITE));
    }

    private function assertNoTransportScheduleConflict(
        AgencyEmbarkation $embarkation,
        AgencyTransport $transport,
        \DateTimeImmutable $date,
    ): void {
        $start = $this->windowStartMinutes((string) $embarkation->getDepartureTime());
        $end = $this->windowEndMinutes($embarkation);

        foreach ($this->embarkations->findByTransportOnDate($transport, $date) as $other) {
            if ($other->getId() === $embarkation->getId()) {
                continue;
            }
            $otherStart = $this->windowStartMinutes((string) $other->getDepartureTime());
            $otherEnd = $this->windowEndMinutes($other);
            if ($this->windowsOverlap($start, $end, $otherStart, $otherEnd)) {
                throw new ConflictException(sprintf(
                    'TRANSPORT_SCHEDULE_CONFLICT: Transport already assigned to course %s on overlapping schedule.',
                    (string) $other->getId(),
                ));
            }
        }
    }

    private function assertNoDriverScheduleConflict(
        AgencyEmbarkation $embarkation,
        AgencyDriver $driver,
        \DateTimeImmutable $date,
    ): void {
        foreach ($this->embarkations->findByDriverOnDate($driver, $date) as $other) {
            if ($other->getId() === $embarkation->getId()) {
                continue;
            }
            throw new ConflictException(sprintf(
                'DRIVER_SCHEDULE_CONFLICT: Driver already assigned to course %s on the same date.',
                (string) $other->getId(),
            ));
        }
    }

    private function windowStartMinutes(string $hhmm): int
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $hhmm, $m)) {
            return 0;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    private function windowEndMinutes(AgencyEmbarkation $embarkation): int
    {
        $start = $this->windowStartMinutes((string) $embarkation->getDepartureTime());
        $duration = (int) ($embarkation->getOffer()?->getDurationMinutes() ?? self::DEFAULT_DURATION_MINUTES);
        if ($duration <= 0) {
            $duration = self::DEFAULT_DURATION_MINUTES;
        }

        return $start + $duration;
    }

    private function windowsOverlap(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /**
     * @return list<string>
     */
    private function collectWarnings(?AgencyDriver $driver): array
    {
        $warnings = [];
        if (!$driver instanceof AgencyDriver) {
            return $warnings;
        }

        $expires = $driver->getLicenseExpiresAt();
        if ($expires instanceof \DateTimeImmutable) {
            $limit = (new \DateTimeImmutable('today'))->modify('+7 days');
            if ($expires <= $limit) {
                $warnings[] = 'DRIVER_LICENSE_EXPIRES_IN_7D';
            }
        }

        return $warnings;
    }

    private function openAssignment(
        AgencyEmbarkation $embarkation,
        AgencyTransport $transport,
        ?AgencyDriver $driver,
        ?string $reason,
    ): AgencyTripAssignment {
        $assignment = new AgencyTripAssignment();
        $assignment->setAgency($embarkation->getAgency());
        $assignment->setEmbarkation($embarkation);
        $assignment->setTransport($transport);
        $assignment->setDriver($driver);
        $assignment->setAssignedAt(new \DateTimeImmutable('now'));
        $assignment->setReason(null !== $reason && '' !== trim($reason) ? trim($reason) : null);
        $user = $this->agencyContext->getUser();
        if ($user instanceof User) {
            $assignment->setAssignedBy($user);
        }
        $this->em->persist($assignment);

        return $assignment;
    }

    /**
     * When ASSIGNED_SEAT / INTERCITY seats are sold, ensure they fit the new layout.
     * Compatible seats stay; incompatible ones remap to free seats when force=true.
     *
     * @return list<array{ticketId: string, from: string, to: string}>
     */
    private function resolveSeatRemap(
        AgencyOffer $offer,
        \DateTimeImmutable $date,
        ?AgencyTransport $previousTransport,
        AgencyTransport $newTransport,
        bool $force,
    ): array {
        if (!$offer->requiresAssignedSeat() && !$offer->isIntercityService()) {
            return [];
        }

        if (!$previousTransport instanceof AgencyTransport) {
            return [];
        }

        if ($previousTransport->getId() === $newTransport->getId()) {
            return [];
        }

        // Build layouts from previous (or offer) transport and the new transport.
        $this->seatLayoutBuilder->build(
            (string) $previousTransport->getKind(),
            (int) $previousTransport->getCapacity(),
        );
        $newLayout = $this->seatLayoutBuilder->build(
            (string) $newTransport->getKind(),
            (int) $newTransport->getCapacity(),
        );
        $newSeatSet = array_fill_keys($newLayout['seatIds'], true);

        $tickets = $this->tickets->findForManifest($offer, $date);
        $incompatible = [];
        $compatibleOccupied = [];

        foreach ($tickets as $ticket) {
            $seat = $this->occupancy->normalizeSeat($ticket->getSeatNumber());
            if ('' === $seat) {
                continue;
            }
            if (isset($newSeatSet[$seat])) {
                $compatibleOccupied[$seat] = true;
            } else {
                $incompatible[] = $ticket;
            }
        }

        if ([] === $incompatible) {
            return $this->remapIncompatibleBookingsWithoutTickets(
                $offer,
                $date,
                $newLayout['seatIds'],
                $compatibleOccupied,
                $force,
            );
        }

        if (!$force) {
            $missing = array_map(
                static fn (AgencyTicket $t): string => (string) $t->getSeatNumber(),
                $incompatible,
            );
            throw new ConflictException(sprintf(
                'SEAT_LAYOUT_CONFLICT: Sold seats [%s] are not on the new transport layout. Use force=true to remap.',
                implode(', ', $missing),
            ));
        }

        $freeSeats = [];
        foreach ($newLayout['seatIds'] as $seatId) {
            if (!isset($compatibleOccupied[$seatId])) {
                $freeSeats[] = $seatId;
            }
        }

        if (\count($freeSeats) < \count($incompatible)) {
            throw new ConflictException(sprintf(
                'CAPACITY_BELOW_SOLD: Not enough free seats on new layout to remap (%d needed, %d free).',
                \count($incompatible),
                \count($freeSeats),
            ));
        }

        $remapped = [];
        $freeIdx = 0;
        foreach ($incompatible as $ticket) {
            $from = $this->occupancy->normalizeSeat($ticket->getSeatNumber());
            $to = $freeSeats[$freeIdx++];
            $ticket->setSeatNumber($to);
            $booking = $ticket->getBooking();
            if (null !== $booking) {
                $booking->setSeatNumber($to);
            }
            $compatibleOccupied[$to] = true;
            $remapped[] = [
                'ticketId' => (string) $ticket->getId(),
                'from' => $from,
                'to' => $to,
            ];
        }

        // Also remap booking-only incompatible seats after tickets took free slots
        $bookingRemaps = $this->remapIncompatibleBookingsWithoutTickets(
            $offer,
            $date,
            $newLayout['seatIds'],
            $compatibleOccupied,
            true,
        );

        return array_merge($remapped, $bookingRemaps);
    }

    /**
     * @param list<string>         $newSeatIds
     * @param array<string, true>  $occupied
     *
     * @return list<array{ticketId: string, from: string, to: string}>
     */
    private function remapIncompatibleBookingsWithoutTickets(
        AgencyOffer $offer,
        \DateTimeImmutable $date,
        array $newSeatIds,
        array &$occupied,
        bool $force,
    ): array {
        $newSeatSet = array_fill_keys($newSeatIds, true);
        $now = new \DateTimeImmutable('now');

        /** @var list<AgencyBooking> $activeBookings */
        $activeBookings = $this->bookings->createQueryBuilder('b')
            ->andWhere('b.offer = :offer')
            ->andWhere('b.travelDate = :travelDate')
            ->andWhere('b.status != :cancelled')
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt >= :now')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $date)
            ->setParameter('cancelled', AgencyBooking::STATUS_CANCELLED)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $incompatible = [];
        foreach ($activeBookings as $booking) {
            // Skip bookings that already have a ticket (handled via ticket remap).
            if (null !== $booking->getTicket()) {
                continue;
            }
            $seat = $this->occupancy->normalizeSeat($booking->getSeatNumber());
            if ('' === $seat || isset($newSeatSet[$seat])) {
                if ('' !== $seat) {
                    $occupied[$seat] = true;
                }
                continue;
            }
            $incompatible[] = $booking;
        }

        if ([] === $incompatible) {
            return [];
        }

        if (!$force) {
            throw new ConflictException(sprintf(
                'SEAT_LAYOUT_CONFLICT: Sold seat %s is not on the new transport layout. Use force=true to remap.',
                (string) $incompatible[0]->getSeatNumber(),
            ));
        }

        $freeSeats = [];
        foreach ($newSeatIds as $seatId) {
            if (!isset($occupied[$seatId])) {
                $freeSeats[] = $seatId;
            }
        }

        if (\count($freeSeats) < \count($incompatible)) {
            throw new ConflictException(sprintf(
                'CAPACITY_BELOW_SOLD: Not enough free seats on new layout to remap (%d needed, %d free).',
                \count($incompatible),
                \count($freeSeats),
            ));
        }

        $remapped = [];
        $freeIdx = 0;
        foreach ($incompatible as $booking) {
            $from = $this->occupancy->normalizeSeat($booking->getSeatNumber());
            $to = $freeSeats[$freeIdx++];
            $booking->setSeatNumber($to);
            $occupied[$to] = true;
            $remapped[] = [
                'ticketId' => (string) $booking->getId(),
                'from' => $from,
                'to' => $to,
            ];
        }

        return $remapped;
    }

    /**
     * @param list<string>                                              $warnings
     * @param list<array{ticketId: string, from: string, to: string}>  $remappedSeats
     */
    private function toAssignResult(
        AgencyEmbarkation $embarkation,
        ?AgencyTripAssignment $assignment,
        array $warnings,
        array $remappedSeats = [],
    ): TripAssignResultDto {
        $transport = $embarkation->getTransport();
        $driver = $embarkation->getDriver();

        return new TripAssignResultDto(
            tripId: (string) $embarkation->getId(),
            embarkationId: (string) $embarkation->getId(),
            status: null !== $transport ? 'ASSIGNED' : $embarkation->getStatus(),
            transport: null === $transport ? null : [
                'id' => (string) $transport->getId(),
                'label' => (string) $transport->getLabel(),
                'plateNumber' => (string) $transport->getPlateNumber(),
                'capacity' => (int) $transport->getCapacity(),
            ],
            driver: null === $driver ? null : [
                'id' => (string) $driver->getId(),
                'name' => (string) $driver->getFullName(),
            ],
            warnings: $warnings,
            assignmentId: $assignment?->getId(),
            remappedSeats: $remappedSeats,
        );
    }

    private function lineFromTicket(AgencyTicket $ticket): DeclarationLine
    {
        $offer = $ticket->getOffer();
        $quote = $this->pricing->quote($ticket->getOkapiPassRef());

        $line = new DeclarationLine();
        $line->setReferenceBillet((string) $ticket->getReference());
        $line->setDate($ticket->getTravelDate());
        $line->setPassengerName((string) $ticket->getPassengerName());
        $line->setPassengerId((string) $ticket->getPassengerId());
        $line->setOrigin((string) ($offer?->getOrigin() ?? ''));
        $line->setDestination((string) ($offer?->getDestination() ?? ''));
        $line->setTicketPrice($ticket->getTicketPrice());
        $line->setCurrency($ticket->getCurrency());
        $line->setPassPrice($ticket->getPassPrice() > 0 ? $ticket->getPassPrice() : $quote['passPrice']);
        $line->setOkapiPassRef($ticket->getOkapiPassRef());
        $line->setHasExistingPass($ticket->hasExistingPass());

        return $line;
    }

    private function resolveOffer(string $ref, ?string $agencyId): AgencyOffer
    {
        $id = $this->extractId($ref);
        $offer = $this->offers->find($id);
        if (null === $offer || $offer->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException('Offer not found.');
        }

        return $offer;
    }

    private function resolveTransport(string $ref, ?string $agencyId): AgencyTransport
    {
        $id = $this->extractId($ref);
        $transport = $this->transports->find($id);
        if (null === $transport || $transport->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException('Transport not found.');
        }

        return $transport;
    }

    private function resolveTicket(string $ref, ?string $agencyId): AgencyTicket
    {
        $id = $this->extractId($ref);
        $ticket = $this->tickets->find($id);
        if (null === $ticket || $ticket->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('Ticket "%s" not found.', $id));
        }

        return $ticket;
    }

    private function extractId(string $ref): string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));

            return (string) end($parts);
        }

        return $ref;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $d) {
            throw new UnprocessableEntityException('Invalid departureDate.');
        }

        return $d->setTime(0, 0);
    }
}
