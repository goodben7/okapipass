<?php

namespace App\Manager;

use App\ApiResource\Public\PublicAgencyBookingGroupResource;
use App\ApiResource\Public\PublicAgencyBookingGroupTicketsResource;
use App\ApiResource\Public\PublicAgencyTicketResource;
use App\Domain\Agency\AgencyPricingService;
use App\Domain\Agency\AgencyTicketIssuanceService;
use App\Domain\Agency\AgencyTransportAvailabilityService;
use App\Domain\Agency\SeatOccupancyService;
use App\Domain\PublicAgency\PublicAgencyBookingTokenGenerator;
use App\Domain\PublicAgency\PublicAgencyCatalogService;
use App\Domain\PublicAgency\PublicAgencyGroupBookingMapper;
use App\Dto\Public\CreatePublicAgencyBookingGroupDto;
use App\Dto\Public\PublicAgencyPassengerLineDto;
use App\Entity\AgencyBooking;
use App\Entity\AgencyBookingGroup;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTransport;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingGroupRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class PublicAgencyGroupBookingManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyBookingGroupRepository $groups,
        private PublicAgencyCatalogService $catalog,
        private SeatOccupancyService $occupancy,
        private AgencyPricingService $pricing,
        private AgencyTicketIssuanceService $ticketIssuance,
        private PublicAgencyBookingTokenGenerator $tokenGenerator,
        private PublicAgencyGroupBookingMapper $mapper,
        private AgencyTransportAvailabilityService $transportAvailability,
    ) {
    }

    public function createOnline(CreatePublicAgencyBookingGroupDto $dto): PublicAgencyBookingGroupResource
    {
        $offer = $this->catalog->requireOnlineOffer((string) $dto->offerId);
        $this->ticketIssuance->assertOfferSellable($offer);

        if (!$offer->isOnlineSales()) {
            throw new UnprocessableEntityException('This offer is not available for online booking.');
        }

        $travelDate = $this->parseTravelDate((string) $dto->travelDate);
        $transport = $offer->getTransport();
        if ($transport instanceof AgencyTransport) {
            $this->transportAvailability->assertAvailableForTravelDate($transport, $travelDate);
        }

        /** @var list<PublicAgencyPassengerLineDto> $passengerLines */
        $passengerLines = $dto->passengers;
        $seatNumbers = array_map(static fn (PublicAgencyPassengerLineDto $line): ?string => $line->seatNumber, $passengerLines);
        $lineQuotes = [];
        foreach ($passengerLines as $index => $line) {
            $quote = $this->pricing->quote($line->okapiPassRef);
            $ticketPrice = (int) $offer->getTicketPrice();
            $passPrice = (int) $quote['passPrice'];
            $lineQuotes[$index] = [
                'ticketPrice' => $ticketPrice,
                'passPrice' => $passPrice,
                'total' => $ticketPrice + $passPrice,
                'currency' => $offer->getCurrency(),
                'hasExistingPass' => (bool) $quote['hasExistingPass'],
            ];
        }

        $holdMinutes = max(1, $offer->getBookingHoldMinutes());
        $expiresAt = new \DateTimeImmutable(sprintf('+%d minutes', $holdMinutes));

        $this->em->beginTransaction();
        try {
            $this->em->lock($offer, LockMode::PESSIMISTIC_WRITE);
            $seats = $this->occupancy->assertSeatsSelectable($offer, $travelDate, $seatNumbers);

            $group = new AgencyBookingGroup();
            $group->setAgency($offer->getAgency());
            $group->setOffer($offer);
            $group->setGroupName(trim((string) $dto->groupName));
            $group->setContactPhone($this->normalizeOptionalPhone($dto->contactPhone));
            $group->setTravelDate($travelDate);
            $group->setStatus(AgencyBookingGroup::STATUS_PENDING);
            $group->setChannel(AgencyBookingGroup::CHANNEL_ONLINE);
            $group->setPaymentStatus(AgencyBookingGroup::PAYMENT_STATUS_UNPAID);
            $group->setPublicToken($this->tokenGenerator->generate());
            $group->setExpiresAt($expiresAt);

            $this->em->persist($group);

            foreach ($passengerLines as $index => $line) {
                $seat = $seats[$index];
                $booking = new AgencyBooking();
                $booking->setAgency($offer->getAgency());
                $booking->setOffer($offer);
                $booking->setPassengerName($this->resolveLinePassengerName($line, $group->getGroupName(), $seat));
                $booking->setPassengerId($this->resolveLinePassengerId($line, $seat));
                $booking->setPassengerPhone($this->resolveLinePassengerPhone($line));
                $booking->setSeatNumber($seat);
                $booking->setTravelDate($travelDate);
                $booking->setOkapiPassRef($line->okapiPassRef);
                $booking->setStatus(AgencyBookingGroup::STATUS_PENDING);
                $booking->setChannel(AgencyBooking::CHANNEL_ONLINE);
                $booking->setPaymentStatus(AgencyBookingGroup::PAYMENT_STATUS_UNPAID);
                $booking->setExpiresAt($expiresAt);
                $booking->setBookingGroup($group);
                $group->addBooking($booking);
                $this->em->persist($booking);
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->mapper->fromGroupWithLineQuotes($group, $lineQuotes);
    }

    public function getByPublicToken(string $publicToken): PublicAgencyBookingGroupResource
    {
        $group = $this->requireOnlineGroupByToken($publicToken);
        $this->expireIfNeeded($group);

        return $this->buildResourceWithQuotes($group);
    }

    public function cancelIfUnpaid(string $publicToken): PublicAgencyBookingGroupResource
    {
        $group = $this->requireOnlineGroupByToken($publicToken);
        $this->expireIfNeeded($group);

        if ($group->isCancelled()) {
            return $this->buildResourceWithQuotes($group);
        }

        if (AgencyBookingGroup::STATUS_PENDING !== $group->getStatus()) {
            throw new UnprocessableEntityException('Only pending online booking groups can be cancelled.');
        }

        if (!\in_array($group->getPaymentStatus(), [
            AgencyBookingGroup::PAYMENT_STATUS_UNPAID,
            AgencyBookingGroup::PAYMENT_STATUS_FAILED,
        ], true)) {
            throw new ConflictException('Cannot cancel a booking group while payment is in progress or completed.');
        }

        $this->cancelGroup($group);
        $this->em->flush();

        return $this->buildResourceWithQuotes($group);
    }

    public function expirePendingGroups(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now');
        $expired = $this->groups->findExpiredOnlinePending($now);
        $count = 0;

        foreach ($expired as $group) {
            $this->cancelGroup($group);
            ++$count;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    public function getTicketByPublicToken(string $publicToken): PublicAgencyTicketResource
    {
        $group = $this->requireOnlineGroupByToken($publicToken);
        $this->expireIfNeeded($group);

        if (AgencyBookingGroup::PAYMENT_STATUS_PAID !== $group->getPaymentStatus()) {
            throw new UnavailableDataException('Ticket not available yet.');
        }

        $ticket = $group->getTicket();
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('Ticket not available yet.');
        }

        return $this->toGroupTicketResource($group, $ticket);
    }

    /** @deprecated Use getTicketByPublicToken — returns single grouped ticket */
    public function getTicketsByPublicToken(string $publicToken): PublicAgencyBookingGroupTicketsResource
    {
        $ticketResource = $this->getTicketByPublicToken($publicToken);
        $group = $this->requireOnlineGroupByToken($publicToken);

        return new PublicAgencyBookingGroupTicketsResource(
            publicToken: (string) $group->getPublicToken(),
            groupId: (string) $group->getId(),
            groupName: (string) $group->getGroupName(),
            ticket: $ticketResource,
        );
    }

    public function requireOnlineGroupByToken(string $publicToken): AgencyBookingGroup
    {
        $token = trim($publicToken);
        if ('' === $token) {
            throw new UnavailableDataException('Booking group not found.');
        }

        $group = $this->groups->findOneByPublicToken($token);
        if (!$group instanceof AgencyBookingGroup) {
            throw new UnavailableDataException('Booking group not found.');
        }

        return $group;
    }

    private function expireIfNeeded(AgencyBookingGroup $group): void
    {
        if (!$this->shouldExpire($group)) {
            return;
        }

        $this->cancelGroup($group);
        $this->em->flush();
    }

    private function shouldExpire(AgencyBookingGroup $group): bool
    {
        if (AgencyBookingGroup::STATUS_PENDING !== $group->getStatus()) {
            return false;
        }

        if (!\in_array($group->getPaymentStatus(), [
            AgencyBookingGroup::PAYMENT_STATUS_UNPAID,
            AgencyBookingGroup::PAYMENT_STATUS_PENDING,
            AgencyBookingGroup::PAYMENT_STATUS_FAILED,
        ], true)) {
            return false;
        }

        return $group->isExpired();
    }

    private function cancelGroup(AgencyBookingGroup $group): void
    {
        if (AgencyBookingGroup::STATUS_CANCELLED === $group->getStatus()) {
            return;
        }

        $group->setStatus(AgencyBookingGroup::STATUS_CANCELLED);
        if (AgencyBookingGroup::PAYMENT_STATUS_PAID !== $group->getPaymentStatus()) {
            $group->setPaymentStatus(AgencyBookingGroup::PAYMENT_STATUS_FAILED);
        }
        $group->syncChildBookingStates();
    }

    private function buildResourceWithQuotes(AgencyBookingGroup $group): PublicAgencyBookingGroupResource
    {
        $offer = $group->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new \LogicException('Group has no offer.');
        }

        $lineQuotes = [];
        $index = 0;
        foreach ($group->getBookings() as $booking) {
            $pricingQuote = $this->pricing->quote($booking->getOkapiPassRef());
            $ticketPrice = (int) $offer->getTicketPrice();
            $passPrice = null !== $booking->getTicket()
                ? (int) $booking->getTicket()->getPassPrice()
                : (int) $pricingQuote['passPrice'];

            $lineQuotes[$index++] = [
                'ticketPrice' => $ticketPrice,
                'passPrice' => $passPrice,
                'total' => $ticketPrice + $passPrice,
                'currency' => (string) $offer->getCurrency(),
                'hasExistingPass' => null !== $booking->getTicket()
                    ? $booking->getTicket()->hasExistingPass()
                    : (bool) $pricingQuote['hasExistingPass'],
            ];
        }

        return $this->mapper->fromGroupWithLineQuotes($group, $lineQuotes);
    }

    private function toGroupTicketResource(AgencyBookingGroup $group, AgencyTicket $ticket): PublicAgencyTicketResource
    {
        $offer = $ticket->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new \LogicException('Ticket has no offer.');
        }

        return new PublicAgencyTicketResource(
            publicToken: (string) $group->getPublicToken(),
            ticketId: (string) $ticket->getId(),
            reference: (string) $ticket->getReference(),
            status: $ticket->getStatus(),
            passengerName: (string) $ticket->getPassengerName(),
            passengerPhone: (string) $ticket->getPassengerPhone(),
            seatNumber: (string) ($ticket->getGroupSeats() ?? $ticket->getSeatNumber()),
            travelDate: $ticket->getTravelDate()?->format('Y-m-d') ?? '',
            ticketPrice: (int) $ticket->getTicketPrice(),
            passPrice: (int) $ticket->getPassPrice(),
            currency: (string) $ticket->getCurrency(),
            hasExistingPass: $ticket->hasExistingPass(),
            qrPayload: $ticket->getQrPayload(),
            offer: [
                'id' => (string) $offer->getId(),
                'label' => (string) $offer->getLabel(),
                'origin' => (string) $offer->getOrigin(),
                'destination' => (string) $offer->getDestination(),
                'departureTime' => (string) $offer->getDepartureTime(),
            ],
            pdfUrl: sprintf(
                '/api/public/agency/booking-groups/%s/ticket/pdf',
                $group->getPublicToken(),
            ),
            isGroupTicket: true,
            groupName: (string) $group->getGroupName(),
            groupSeats: $ticket->getGroupSeatList(),
            passengerCount: $group->getBookings()->count(),
        );
    }

    private function resolveLinePassengerName(PublicAgencyPassengerLineDto $line, ?string $groupName, string $seat): string
    {
        $name = trim((string) ($line->passengerName ?? ''));

        return '' !== $name ? $name : '—';
    }

    private function resolveLinePassengerId(PublicAgencyPassengerLineDto $line, string $seat): string
    {
        $id = trim((string) ($line->passengerId ?? ''));

        return '' !== $id ? $id : 'SEAT-'.$seat;
    }

    private function resolveLinePassengerPhone(PublicAgencyPassengerLineDto $line): string
    {
        return trim((string) ($line->passengerPhone ?? ''));
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

    private function normalizeOptionalPhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        return '' === $phone ? null : $phone;
    }
}
