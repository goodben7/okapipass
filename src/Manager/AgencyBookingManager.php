<?php

namespace App\Manager;

use App\Domain\Agency\AgencyNotificationTextBuilder;
use App\Domain\Agency\AgencyPricingService;
use App\Domain\Agency\AgencyQrPayloadBuilder;
use App\Domain\Agency\AgencyTicketIssuanceService;
use App\Domain\Agency\AgencyTicketReferenceGenerator;
use App\Domain\Agency\AgencyTransportAvailabilityService;
use App\Domain\Agency\SeatOccupancyService;
use App\Dto\Agency\AgencyBookingCreateResult;
use App\Dto\Agency\AgencyTicketCreateResult;
use App\Dto\Agency\CreateAgencyBookingDto;
use App\Dto\Agency\CreateAgencyTicketDto;
use App\Dto\Agency\UpdateAgencyBookingDto;
use App\Dto\Agency\UpdateAgencyTicketSeatDto;
use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTransport;
use App\Contract\AgencySmsSenderInterface;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyOfferRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class AgencyBookingManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyBookingRepository $bookings,
        private AgencyOfferRepository $offers,
        private SeatOccupancyService $occupancy,
        private AgencyPricingService $pricing,
        private AgencyTicketReferenceGenerator $references,
        private AgencyQrPayloadBuilder $qrPayloadBuilder,
        private AgencyNotificationTextBuilder $notificationTexts,
        private AgencySmsSenderInterface $smsSender,
        private AgencyTicketIssuanceService $ticketIssuance,
        private AgencyTransportAvailabilityService $transportAvailability,
    ) {
    }

    public function create(CreateAgencyBookingDto $dto): AgencyBookingCreateResult
    {
        $agency = $this->agencyContext->requireAgency();
        $offer = $this->resolveOffer((string) $dto->offer, $agency->getId());
        $this->assertOfferSellable($offer);

        $travelDate = $this->parseDate((string) $dto->travelDate);
        $this->assertTransportAvailableForTravel($offer, $travelDate);

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);
            $seat = $this->occupancy->assertSeatSelectable($offer, $travelDate, $dto->seatNumber);

            $booking = new AgencyBooking();
            $booking->setAgency($agency);
            $booking->setOffer($offer);
            $booking->setPassengerName((string) $dto->passengerName);
            $booking->setPassengerId((string) $dto->passengerId);
            $booking->setPassengerPhone((string) $dto->passengerPhone);
            $booking->setSeatNumber($seat);
            $booking->setTravelDate($travelDate);
            $booking->setOkapiPassRef($dto->okapiPassRef);
            $status = $dto->status ?? AgencyBooking::STATUS_PENDING;
            if (!\in_array($status, [AgencyBooking::STATUS_PENDING, AgencyBooking::STATUS_CONFIRMED], true)) {
                throw new UnprocessableEntityException('Invalid initial booking status.');
            }
            $booking->setStatus($status);
            $booking->setChannel(AgencyBooking::CHANNEL_DESK);
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_UNPAID);

            $this->em->persist($booking);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $smsMessageId = null;
        if ($dto->sendSms ?? false) {
            $smsMessageId = $this->smsSender->send(
                (string) $booking->getPassengerPhone(),
                $this->notificationTexts->bookingSms($booking),
            );
        }

        return new AgencyBookingCreateResult($booking, $smsMessageId);
    }

    public function update(AgencyBooking $booking, UpdateAgencyBookingDto $dto): AgencyBooking
    {
        $this->agencyContext->assertOwns($booking->getAgency());
        if ($booking->isCancelled()) {
            throw new UnprocessableEntityException('Cannot edit a cancelled booking.');
        }
        if (null !== $booking->getTicket()) {
            throw new UnprocessableEntityException('Cannot edit a booking that already has a ticket.');
        }

        $offer = $booking->getOffer();
        $travelDate = $booking->getTravelDate();
        $seatChanged = false;

        if (null !== $dto->travelDate) {
            $travelDate = $this->parseDate($dto->travelDate);
            $this->assertTransportAvailableForTravel($offer, $travelDate);
            $seatChanged = true;
        }
        if (null !== $dto->seatNumber) {
            $seatChanged = true;
        }

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);

            if ($seatChanged) {
                $seat = $this->occupancy->assertSeatSelectable(
                    $offer,
                    $travelDate,
                    $dto->seatNumber ?? $booking->getSeatNumber(),
                    $booking->getId(),
                );
                $booking->setSeatNumber($seat);
                $booking->setTravelDate($travelDate);
            }

            if (null !== $dto->passengerName) {
                $booking->setPassengerName($dto->passengerName);
            }
            if (null !== $dto->passengerId) {
                $booking->setPassengerId($dto->passengerId);
            }
            if (null !== $dto->passengerPhone) {
                $booking->setPassengerPhone($dto->passengerPhone);
            }
            if (null !== $dto->okapiPassRef) {
                $booking->setOkapiPassRef($dto->okapiPassRef);
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $booking;
    }

    public function updateStatus(AgencyBooking $booking, string $status): AgencyBooking
    {
        $this->agencyContext->assertOwns($booking->getAgency());

        if (AgencyBooking::STATUS_CANCELLED === $status) {
            if (null !== $booking->getTicket() && !$booking->getTicket()->isCancelled()) {
                throw new UnprocessableEntityException('Cancel the ticket before cancelling this booking.');
            }
            $booking->setStatus(AgencyBooking::STATUS_CANCELLED);
        } elseif (AgencyBooking::STATUS_CONFIRMED === $status) {
            if (AgencyBooking::STATUS_PENDING !== $booking->getStatus()) {
                throw new UnprocessableEntityException('Only PENDING bookings can be confirmed.');
            }
            $booking->setStatus(AgencyBooking::STATUS_CONFIRMED);
        } else {
            throw new UnprocessableEntityException(sprintf('Unsupported status transition to %s.', $status));
        }

        $this->em->flush();

        return $booking;
    }

    public function issueTicket(AgencyBooking $booking): AgencyTicket
    {
        $this->agencyContext->assertOwns($booking->getAgency());

        return $this->ticketIssuance->issueFromBooking($booking);
    }

    public function createManualTicket(CreateAgencyTicketDto $dto): AgencyTicketCreateResult
    {
        $agency = $this->agencyContext->requireAgency();
        $offer = $this->resolveOffer((string) $dto->offer, $agency->getId());
        $this->assertOfferSellable($offer);
        $travelDate = $this->parseDate((string) $dto->travelDate);
        $this->assertTransportAvailableForTravel($offer, $travelDate);

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);
            $seat = $this->occupancy->assertSeatSelectable($offer, $travelDate, $dto->seatNumber);
            $quote = $this->pricing->quote($dto->okapiPassRef);
            $reference = $this->references->next($agency);

            $ticket = new AgencyTicket();
            $ticket->setAgency($agency);
            $ticket->setOffer($offer);
            $ticket->setReference($reference);
            $ticket->setPassengerName((string) $dto->passengerName);
            $ticket->setPassengerId((string) $dto->passengerId);
            $ticket->setPassengerPhone((string) $dto->passengerPhone);
            $ticket->setSeatNumber($seat);
            $ticket->setTravelDate($travelDate);
            $ticket->setTicketPrice((int) $offer->getTicketPrice());
            $ticket->setPassPrice($quote['passPrice']);
            $ticket->setCurrency($offer->getCurrency());
            $ticket->setOkapiPassRef($dto->okapiPassRef);
            $ticket->setHasExistingPass($quote['hasExistingPass']);
            $ticket->setNotes($dto->notes);
            $ticket->setStatus(AgencyTicket::STATUS_ISSUED);
            $ticket->setQrPayload($this->qrPayloadBuilder->build($ticket));

            $this->em->persist($ticket);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $smsMessageId = null;
        if ($dto->sendSms ?? false) {
            $smsMessageId = $this->smsSender->send(
                (string) $ticket->getPassengerPhone(),
                $this->notificationTexts->ticketSms($ticket),
            );
        }

        return new AgencyTicketCreateResult($ticket, $smsMessageId);
    }

    public function updateTicketStatus(AgencyTicket $ticket, string $status): AgencyTicket
    {
        $this->agencyContext->assertOwns($ticket->getAgency());
        $current = $ticket->getStatus();

        $allowed = match ($current) {
            AgencyTicket::STATUS_ISSUED => [AgencyTicket::STATUS_BOARDED, AgencyTicket::STATUS_CANCELLED],
            AgencyTicket::STATUS_BOARDED => [AgencyTicket::STATUS_USED, AgencyTicket::STATUS_CANCELLED],
            default => [],
        };

        if (!\in_array($status, $allowed, true)) {
            throw new UnprocessableEntityException(sprintf('Cannot transition ticket from %s to %s.', $current, $status));
        }

        $ticket->setStatus($status);

        $booking = $ticket->getBooking();
        if (null !== $booking) {
            if (AgencyTicket::STATUS_CANCELLED === $status) {
                $booking->setStatus(AgencyBooking::STATUS_CANCELLED);
            } elseif (\in_array($status, [AgencyTicket::STATUS_BOARDED, AgencyTicket::STATUS_USED], true)) {
                $booking->setStatus(AgencyBooking::STATUS_COMPLETED);
            }
        }

        $this->em->flush();

        return $ticket;
    }

    public function updateTicketSeat(AgencyTicket $ticket, UpdateAgencyTicketSeatDto $dto): AgencyTicket
    {
        $this->agencyContext->assertOwns($ticket->getAgency());
        if ($ticket->isCancelled()) {
            throw new UnprocessableEntityException('Cannot change seat on a cancelled ticket.');
        }

        $offer = $ticket->getOffer();
        $excludeBookingId = $ticket->getBooking()?->getId();

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);
            $seat = $this->occupancy->assertSeatSelectable(
                $offer,
                $ticket->getTravelDate(),
                (string) $dto->seatNumber,
                $excludeBookingId,
                $ticket->getSeatNumber(),
            );
            $ticket->setSeatNumber($seat);
            if (null !== $ticket->getBooking()) {
                $ticket->getBooking()->setSeatNumber($seat);
            }
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $ticket;
    }

    private function assertOfferSellable(AgencyOffer $offer): void
    {
        $this->ticketIssuance->assertOfferSellable($offer);
    }

    private function assertTransportAvailableForTravel(AgencyOffer $offer, \DateTimeImmutable $travelDate): void
    {
        $transport = $offer->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->transportAvailability->assertAvailableForTravelDate($transport, $travelDate);
        }
    }

    private function resolveOffer(string $ref, ?string $agencyId): AgencyOffer
    {
        $id = $this->extractId($ref);
        $offer = $this->offers->find($id);
        if (null === $offer || $offer->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('Offer "%s" not found.', $id));
        }

        return $offer;
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
            throw new UnprocessableEntityException('Invalid travelDate, expected YYYY-MM-DD.');
        }

        return $d->setTime(0, 0);
    }
}
