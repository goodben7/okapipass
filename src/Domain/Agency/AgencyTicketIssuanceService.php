<?php

namespace App\Domain\Agency;

use App\Contract\AgencySmsSenderInterface;
use App\Entity\AgencyBooking;
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
