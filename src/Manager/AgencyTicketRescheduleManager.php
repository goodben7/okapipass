<?php

namespace App\Manager;

use App\Domain\Agency\AgencyOfferEffectivePriceResolver;
use App\Domain\Agency\AgencyPermission;
use App\Domain\Agency\AgencyQrPayloadBuilder;
use App\Domain\Agency\AgencyTransportAvailabilityService;
use App\Domain\Agency\SeatOccupancyService;
use App\Dto\Agency\RescheduleAgencyTicketDto;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTransport;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyOfferRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyTicketRescheduleManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyOfferRepository $offers,
        private SeatOccupancyService $occupancy,
        private AgencyTransportAvailabilityService $transportAvailability,
        private AgencyOfferEffectivePriceResolver $effectivePrice,
        private AgencyQrPayloadBuilder $qrPayloadBuilder,
        private AgencyAuditLogManager $auditLog,
    ) {
    }

    public function reschedule(AgencyTicket $ticket, RescheduleAgencyTicketDto $dto): AgencyTicket
    {
        $this->agencyContext->requirePermission(AgencyPermission::TICKET_WRITE);
        $this->agencyContext->assertOwns($ticket->getAgency());

        if (AgencyTicket::STATUS_ISSUED !== $ticket->getStatus()) {
            throw new UnprocessableEntityException('Only ISSUED tickets can be rescheduled.');
        }

        $travelDate = $this->parseDate((string) $dto->travelDate);
        $offer = $ticket->getOffer();
        if (null !== $dto->offerId && '' !== trim($dto->offerId)) {
            $offerId = $this->extractId($dto->offerId);
            $offer = $this->offers->find($offerId);
            if (!$offer instanceof AgencyOffer || $offer->getAgency()?->getId() !== $ticket->getAgency()?->getId()) {
                throw new UnavailableDataException('Offer not found.');
            }
        }
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('Ticket has no offer.');
        }

        $transport = $offer->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->transportAvailability->assertAvailableForTravelDate($transport, $travelDate);
        }

        $seat = $dto->seatNumber ?? $ticket->getSeatNumber();
        $excludeBookingId = $ticket->getBooking()?->getId();
        $fee = (int) ($ticket->getAgency()?->getRescheduleFeeFlat() ?? 0);

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);
            $seat = $this->occupancy->assertSeatSelectable(
                $offer,
                $travelDate,
                (string) $seat,
                $excludeBookingId,
                $ticket->getOffer()?->getId() === $offer->getId()
                    && $ticket->getTravelDate()?->format('Y-m-d') === $travelDate->format('Y-m-d')
                    ? $ticket->getSeatNumber()
                    : null,
            );

            $ticket->setOffer($offer);
            $ticket->setTravelDate($travelDate);
            $ticket->setSeatNumber($seat);
            $ticket->setTicketPrice($this->effectivePrice->resolve($offer, $travelDate));
            $ticket->setRescheduleFee($fee);
            $this->qrPayloadBuilder->refreshToken($ticket);
            $ticket->setQrPayload($this->qrPayloadBuilder->build($ticket));

            if (null !== $ticket->getBooking()) {
                $booking = $ticket->getBooking();
                $booking->setOffer($offer);
                $booking->setTravelDate($travelDate);
                $booking->setSeatNumber($seat);
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $agency = $ticket->getAgency();
        if (null !== $agency) {
            $this->auditLog->log(
                $agency,
                $this->agencyContext->getUser(),
                'ticket.reschedule',
                'AgencyTicket',
                (string) $ticket->getId(),
                ['travelDate' => $travelDate->format('Y-m-d'), 'offerId' => $offer->getId(), 'fee' => $fee],
            );
        }

        return $ticket;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $d) {
            throw new UnprocessableEntityException('Invalid travelDate, expected YYYY-MM-DD.');
        }

        return $d->setTime(0, 0);
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
}
