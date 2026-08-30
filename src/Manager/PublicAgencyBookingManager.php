<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPricingService;
use App\Domain\Agency\AgencyTicketIssuanceService;
use App\Domain\Agency\SeatOccupancyService;
use App\Domain\PublicAgency\PublicAgencyBookingMapper;
use App\Domain\PublicAgency\PublicAgencyBookingTokenGenerator;
use App\Domain\PublicAgency\PublicAgencyCatalogService;
use App\ApiResource\Public\PublicAgencyBookingResource;
use App\Dto\Public\CreatePublicAgencyBookingDto;
use App\Entity\AgencyBooking;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class PublicAgencyBookingManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyBookingRepository $bookings,
        private PublicAgencyCatalogService $catalog,
        private SeatOccupancyService $occupancy,
        private AgencyPricingService $pricing,
        private AgencyTicketIssuanceService $ticketIssuance,
        private PublicAgencyBookingTokenGenerator $tokenGenerator,
        private PublicAgencyBookingMapper $mapper,
    ) {
    }

    public function createOnline(CreatePublicAgencyBookingDto $dto): PublicAgencyBookingResource
    {
        $offer = $this->catalog->requireOnlineOffer((string) $dto->offerId);
        $this->ticketIssuance->assertOfferSellable($offer);

        if (!$offer->isOnlineSales()) {
            throw new UnprocessableEntityException('This offer is not available for online booking.');
        }

        $travelDate = $this->parseTravelDate((string) $dto->travelDate);
        $quote = $this->pricing->quote($dto->okapiPassRef);
        $ticketPrice = (int) $offer->getTicketPrice();
        $passPrice = (int) $quote['passPrice'];
        $quotePayload = [
            'ticketPrice' => $ticketPrice,
            'passPrice' => $passPrice,
            'total' => $ticketPrice + $passPrice,
            'currency' => $offer->getCurrency(),
            'hasExistingPass' => (bool) $quote['hasExistingPass'],
        ];

        $holdMinutes = max(1, $offer->getBookingHoldMinutes());
        $expiresAt = new \DateTimeImmutable(sprintf('+%d minutes', $holdMinutes));

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);
            $seat = $this->occupancy->assertSeatSelectable($offer, $travelDate, $dto->seatNumber);

            $booking = new AgencyBooking();
            $booking->setAgency($offer->getAgency());
            $booking->setOffer($offer);
            $booking->setPassengerName((string) $dto->passengerName);
            $booking->setPassengerId((string) $dto->passengerId);
            $booking->setPassengerPhone((string) $dto->passengerPhone);
            $booking->setSeatNumber($seat);
            $booking->setTravelDate($travelDate);
            $booking->setOkapiPassRef($dto->okapiPassRef);
            $booking->setStatus(AgencyBooking::STATUS_PENDING);
            $booking->setChannel(AgencyBooking::CHANNEL_ONLINE);
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_UNPAID);
            $booking->setPublicToken($this->tokenGenerator->generate());
            $booking->setExpiresAt($expiresAt);

            $this->em->persist($booking);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->mapper->fromBookingWithQuote($booking, $quotePayload);
    }

    public function getByPublicToken(string $publicToken): PublicAgencyBookingResource
    {
        $booking = $this->requireOnlineBookingByToken($publicToken);
        $this->expireIfNeeded($booking);

        return $this->buildResourceWithQuote($booking);
    }

    public function cancelIfUnpaid(string $publicToken): PublicAgencyBookingResource
    {
        $booking = $this->requireOnlineBookingByToken($publicToken);
        $this->expireIfNeeded($booking);

        if ($booking->isCancelled()) {
            return $this->buildResourceWithQuote($booking);
        }

        if (AgencyBooking::STATUS_PENDING !== $booking->getStatus()) {
            throw new UnprocessableEntityException('Only pending online bookings can be cancelled.');
        }

        if (!\in_array($booking->getPaymentStatus(), [
            AgencyBooking::PAYMENT_STATUS_UNPAID,
            AgencyBooking::PAYMENT_STATUS_FAILED,
        ], true)) {
            throw new ConflictException('Cannot cancel a booking while payment is in progress or completed.');
        }

        $booking->setStatus(AgencyBooking::STATUS_CANCELLED);
        if (AgencyBooking::PAYMENT_STATUS_PAID !== $booking->getPaymentStatus()) {
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_FAILED);
        }
        $this->em->flush();

        return $this->buildResourceWithQuote($booking);
    }

    public function expirePendingBookings(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now');
        $expired = $this->bookings->findExpiredOnlinePending($now);
        $count = 0;

        foreach ($expired as $booking) {
            $this->cancelExpired($booking);
            ++$count;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    private function requireOnlineBookingByToken(string $publicToken): AgencyBooking
    {
        $token = trim($publicToken);
        if ('' === $token) {
            throw new UnavailableDataException('Booking not found.');
        }

        $booking = $this->bookings->findOneByPublicToken($token);
        if (!$booking instanceof AgencyBooking || AgencyBooking::CHANNEL_ONLINE !== $booking->getChannel()) {
            throw new UnavailableDataException('Booking not found.');
        }

        return $booking;
    }

    private function expireIfNeeded(AgencyBooking $booking): void
    {
        if (!$this->shouldExpire($booking)) {
            return;
        }

        $this->cancelExpired($booking);
        $this->em->flush();
    }

    private function shouldExpire(AgencyBooking $booking): bool
    {
        if (AgencyBooking::STATUS_PENDING !== $booking->getStatus()) {
            return false;
        }

        if (!\in_array($booking->getPaymentStatus(), [
            AgencyBooking::PAYMENT_STATUS_UNPAID,
            AgencyBooking::PAYMENT_STATUS_PENDING,
            AgencyBooking::PAYMENT_STATUS_FAILED,
        ], true)) {
            return false;
        }

        return $booking->isExpired();
    }

    private function cancelExpired(AgencyBooking $booking): void
    {
        if (AgencyBooking::STATUS_CANCELLED === $booking->getStatus()) {
            return;
        }

        $booking->setStatus(AgencyBooking::STATUS_CANCELLED);
        if (AgencyBooking::PAYMENT_STATUS_PAID !== $booking->getPaymentStatus()) {
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_FAILED);
        }
    }

    /**
     * @return array{ticketPrice: int, passPrice: int, total: int, currency: string, hasExistingPass: bool}
     */
    private function buildResourceWithQuote(AgencyBooking $booking): PublicAgencyBookingResource
    {
        $offer = $booking->getOffer();
        $ticketPrice = (int) ($offer?->getTicketPrice() ?? 0);
        $pricingQuote = $this->pricing->quote($booking->getOkapiPassRef());
        $passPrice = (int) $pricingQuote['passPrice'];

        if (null !== $booking->getTicket()) {
            $passPrice = (int) $booking->getTicket()->getPassPrice();
        }

        $quotePayload = [
            'ticketPrice' => $ticketPrice,
            'passPrice' => $passPrice,
            'total' => $ticketPrice + $passPrice,
            'currency' => (string) ($offer?->getCurrency() ?? 'CDF'),
            'hasExistingPass' => null !== $booking->getTicket()
                ? $booking->getTicket()->hasExistingPass()
                : (bool) $pricingQuote['hasExistingPass'],
        ];

        return $this->mapper->fromBookingWithQuote($booking, $quotePayload);
    }

    private function parseTravelDate(string $date): \DateTimeImmutable
    {
        $travelDate = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $travelDate) {
            throw new UnprocessableEntityException('Invalid travelDate, expected YYYY-MM-DD.');
        }

        $travelDate = $travelDate->setTime(0, 0);
        if ($travelDate < new \DateTimeImmutable('today')) {
            throw new UnprocessableEntityException('travelDate must be today or in the future.');
        }

        return $travelDate;
    }
}
