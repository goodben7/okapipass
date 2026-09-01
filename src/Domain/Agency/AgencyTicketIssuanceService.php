<?php

namespace App\Domain\Agency;

use App\Contract\AgencySmsSenderInterface;
use App\Entity\AgencyBooking;
use App\Entity\AgencyBookingGroup;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Exception\UnprocessableEntityException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Core ticket issuance from a booking — used by partner desk and online payment fulfillment.
 */
final class AgencyTicketIssuanceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SeatOccupancyService $occupancy,
        private AgencyPricingService $pricing,
        private AgencyTicketReferenceGenerator $references,
        private AgencyQrPayloadBuilder $qrPayloadBuilder,
        private AgencyNotificationTextBuilder $notificationTexts,
        private AgencySmsSenderInterface $smsSender,
    ) {
    }

    public function issueFromBooking(AgencyBooking $booking, bool $sendSms = true): AgencyTicket
    {
        if ($booking->isCancelled()) {
            throw new UnprocessableEntityException('Cannot issue ticket for a cancelled booking.');
        }

        if (null !== $booking->getTicket()) {
            return $booking->getTicket();
        }

        $offer = $booking->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('Booking has no offer.');
        }

        $this->assertOfferSellable($offer);

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);
            $this->occupancy->assertSeatSelectable(
                $offer,
                $booking->getTravelDate(),
                $booking->getSeatNumber(),
                $booking->getId(),
            );

            $quote = $this->pricing->quote($booking->getOkapiPassRef());
            $reference = $this->references->next($booking->getAgency());

            $ticket = new AgencyTicket();
            $ticket->setAgency($booking->getAgency());
            $ticket->setOffer($offer);
            $ticket->setReference($reference);
            $ticket->setPassengerName((string) $booking->getPassengerName());
            $ticket->setPassengerId((string) $booking->getPassengerId());
            $ticket->setPassengerPhone((string) $booking->getPassengerPhone());
            $ticket->setSeatNumber((string) $booking->getSeatNumber());
            $ticket->setTravelDate($booking->getTravelDate());
            $ticket->setTicketPrice((int) $offer->getTicketPrice());
            $ticket->setPassPrice($quote['passPrice']);
            $ticket->setCurrency($offer->getCurrency());
            $ticket->setOkapiPassRef($booking->getOkapiPassRef());
            $ticket->setHasExistingPass($quote['hasExistingPass']);
            $ticket->setStatus(AgencyTicket::STATUS_ISSUED);
            $ticket->setQrPayload($this->qrPayloadBuilder->build($ticket));
            $ticket->setBooking($booking);

            $booking->setStatus(AgencyBooking::STATUS_CONFIRMED);
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_PAID);
            $booking->setTicket($ticket);

            $this->em->persist($ticket);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        if ($sendSms) {
            $this->smsSender->send(
                (string) $ticket->getPassengerPhone(),
                $this->notificationTexts->ticketSms($ticket),
            );
        }

        return $ticket;
    }

    public function issueFromGroup(AgencyBookingGroup $group, bool $sendSms = true): AgencyTicket
    {
        if ($group->isCancelled()) {
            throw new UnprocessableEntityException('Cannot issue ticket for a cancelled booking group.');
        }

        if ($group->getTicket() instanceof AgencyTicket) {
            return $group->getTicket();
        }

        $offer = $group->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('Booking group has no offer.');
        }

        $this->assertOfferSellable($offer);

        $bookings = $group->getBookings()->toArray();
        if ([] === $bookings) {
            throw new UnprocessableEntityException('Booking group has no seat reservations.');
        }

        usort($bookings, static fn (AgencyBooking $a, AgencyBooking $b): int => strcmp((string) $a->getSeatNumber(), (string) $b->getSeatNumber()));

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);

            $seats = [];
            $manifest = [];
            $totalTicketPrice = 0;
            $totalPassPrice = 0;
            $hasExistingPass = false;

            foreach ($bookings as $booking) {
                $this->occupancy->assertSeatSelectable(
                    $offer,
                    $group->getTravelDate(),
                    $booking->getSeatNumber(),
                    $booking->getId(),
                );
                $seats[] = (string) $booking->getSeatNumber();
                $quote = $this->pricing->quote($booking->getOkapiPassRef());
                $totalTicketPrice += (int) $offer->getTicketPrice();
                $totalPassPrice += (int) $quote['passPrice'];
                $hasExistingPass = $hasExistingPass || (bool) $quote['hasExistingPass'];
                $manifest[] = [
                    'seat' => (string) $booking->getSeatNumber(),
                    'passengerName' => $booking->getPassengerName(),
                    'passengerId' => $booking->getPassengerId(),
                    'passengerPhone' => $booking->getPassengerPhone(),
                ];
            }

            $reference = $this->references->next($group->getAgency());
            $seatLabel = implode(', ', $seats);

            $ticket = new AgencyTicket();
            $ticket->setAgency($group->getAgency());
            $ticket->setOffer($offer);
            $ticket->setReference($reference);
            $ticket->setPassengerName((string) $group->getGroupName());
            $ticket->setPassengerId((string) $group->getId());
            $ticket->setPassengerPhone((string) ($group->getContactPhone() ?? ''));
            $ticket->setSeatNumber(sprintf('%dP', \count($seats)));
            $ticket->setTravelDate($group->getTravelDate());
            $ticket->setTicketPrice($totalTicketPrice);
            $ticket->setPassPrice($totalPassPrice);
            $ticket->setCurrency($offer->getCurrency());
            $ticket->setHasExistingPass($hasExistingPass);
            $ticket->setStatus(AgencyTicket::STATUS_ISSUED);
            $ticket->setIsGroupTicket(true);
            $ticket->setGroupSeats($seatLabel);
            $ticket->setNotes(json_encode(['groupManifest' => $manifest], \JSON_THROW_ON_ERROR));
            $ticket->setBookingGroup($group);

            foreach ($bookings as $booking) {
                $booking->setStatus(AgencyBooking::STATUS_CONFIRMED);
                $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_PAID);
            }

            $group->setStatus(AgencyBookingGroup::STATUS_CONFIRMED);
            $group->setPaymentStatus(AgencyBookingGroup::PAYMENT_STATUS_PAID);
            $group->setTicket($ticket);
            $group->syncChildBookingStates();

            $ticket->setQrPayload($this->qrPayloadBuilder->build($ticket));

            $this->em->persist($ticket);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $notifyPhone = trim((string) ($group->getContactPhone() ?? ''));
        if ($sendSms && '' !== $notifyPhone) {
            $this->smsSender->send(
                $notifyPhone,
                $this->notificationTexts->ticketSms($ticket),
            );
        }

        return $ticket;
    }

    public function assertOfferSellable(AgencyOffer $offer): void
    {
        if (!$offer->isActive()) {
            throw new UnprocessableEntityException('Offer is not active.');
        }

        $transport = $offer->getTransport();
        if (null === $transport || !$transport->isActiveForSale()) {
            throw new UnprocessableEntityException('Transport is not ACTIVE — sales are blocked.');
        }
    }
}
