<?php

namespace App\Domain\Agency;

use App\Entity\AgencyTicket;
use App\Entity\TravelerPass;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyTicketRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * QR payload for agency tickets (aligned for front qr-utils consumption).
 */
final class AgencyQrPayloadBuilder
{
    public const int TOKEN_TTL_HOURS = 2;
    public const int BOARDING_COOLDOWN_SECONDS = 300;

    public function __construct(
        private ?AgencyTicketRepository $tickets = null,
        private ?EntityManagerInterface $em = null,
    ) {
    }

    public function refreshToken(AgencyTicket $ticket): void
    {
        $ticket->setQrToken(bin2hex(random_bytes(16)));
        $ticket->setQrTokenExpiresAt(new \DateTimeImmutable(sprintf('+%d hours', self::TOKEN_TTL_HOURS)));
        $ticket->setQrTokenUsedAt(null);
    }

    public function build(AgencyTicket $ticket): string
    {
        if (null === $ticket->getQrToken()) {
            $this->refreshToken($ticket);
        }

        $payload = [
            'v' => 1,
            'type' => $ticket->isGroupTicket() ? 'agency_group_ticket' : 'agency_ticket',
            'ref' => $ticket->getReference(),
            'seat' => $ticket->getSeatNumber(),
            'date' => $ticket->getTravelDate()?->format('Y-m-d'),
            'offer' => $ticket->getOffer()?->getId(),
            'agency' => $ticket->getAgency()?->getId(),
            'passenger' => $ticket->getPassengerName(),
            'pass' => $ticket->getOkapiPassRef(),
            'token' => $ticket->getQrToken(),
        ];

        if ($ticket->isGroupTicket()) {
            $payload['seats'] = $ticket->getGroupSeatList();
            $payload['groupId'] = $ticket->getBookingGroup()?->getId();
        }

        return base64_encode(json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function printData(AgencyTicket $ticket): array
    {
        $offer = $ticket->getOffer();

        return [
            'ticket' => [
                'id' => $ticket->getId(),
                'reference' => $ticket->getReference(),
                'status' => $ticket->getStatus(),
                'passengerName' => $ticket->getPassengerName(),
                'passengerId' => $ticket->getPassengerId(),
                'passengerPhone' => $ticket->getPassengerPhone(),
                'seatNumber' => $ticket->getSeatNumber(),
                'travelDate' => $ticket->getTravelDate()?->format('Y-m-d'),
                'ticketPrice' => $ticket->getTicketPrice(),
                'passPrice' => $ticket->getPassPrice(),
                'currency' => $ticket->getCurrency(),
                'okapiPassRef' => $ticket->getOkapiPassRef(),
                'hasExistingPass' => $ticket->hasExistingPass(),
                'notes' => $ticket->getNotes(),
                'qrToken' => $ticket->getQrToken(),
                'qrTokenExpiresAt' => $ticket->getQrTokenExpiresAt()?->format(\DateTimeInterface::ATOM),
            ],
            'offer' => [
                'id' => $offer?->getId(),
                'label' => $offer?->getLabel(),
                'origin' => $offer?->getOrigin(),
                'destination' => $offer?->getDestination(),
                'departureTime' => $offer?->getDepartureTime(),
            ],
            'agency' => [
                'id' => $ticket->getAgency()?->getId(),
                'name' => $ticket->getAgency()?->getName(),
            ],
            'qrPayload' => $ticket->getQrPayload() ?: $this->build($ticket),
        ];
    }

    /**
     * Validate rotating QR token at embarkation; marks usedAt, consumes traveler pass if linked, refreshes token for reprint.
     */
    public function validateAndConsume(string $token): AgencyTicket
    {
        if (null === $this->tickets || null === $this->em) {
            throw new \LogicException('AgencyTicketRepository required for QR validation.');
        }

        $ticket = $this->tickets->findOneByQrToken($token);
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('QR token not found.');
        }

        if (null !== $ticket->getQrTokenUsedAt()) {
            throw new UnprocessableEntityException('QR token already used.');
        }

        $expires = $ticket->getQrTokenExpiresAt();
        if ($expires instanceof \DateTimeImmutable && $expires < new \DateTimeImmutable()) {
            throw new UnprocessableEntityException('QR token expired.');
        }

        if (!\in_array($ticket->getStatus(), [AgencyTicket::STATUS_ISSUED, AgencyTicket::STATUS_BOARDED], true)) {
            throw new UnprocessableEntityException('Ticket cannot be boarded with this token.');
        }

        $now = new \DateTimeImmutable();
        $this->assertBoardingCooldown($ticket, $now);

        $ticket->setQrTokenUsedAt($now);
        $ticket->setLastBoardedAt($now);
        if (AgencyTicket::STATUS_ISSUED === $ticket->getStatus()) {
            $ticket->setStatus(AgencyTicket::STATUS_BOARDED);
        }

        $this->refreshToken($ticket);
        $ticket->setQrPayload($this->build($ticket));
        $this->em->flush();

        return $ticket;
    }

    private function assertBoardingCooldown(AgencyTicket $ticket, \DateTimeImmutable $now): void
    {
        $lastBoarded = $ticket->getLastBoardedAt();
        if ($lastBoarded instanceof \DateTimeImmutable
            && ($now->getTimestamp() - $lastBoarded->getTimestamp()) < self::BOARDING_COOLDOWN_SECONDS
        ) {
            throw new ConflictException('BOARDING_COOLDOWN: Ticket was boarded less than 5 minutes ago.');
        }

        $pass = $ticket->getTravelerPass();
        $offer = $ticket->getOffer();
        if ($pass instanceof TravelerPass
            && null !== $offer
            && $offer->isUrbanService()
        ) {
            $lastConsumed = $pass->getLastConsumedAt();
            if ($lastConsumed instanceof \DateTimeImmutable
                && ($now->getTimestamp() - $lastConsumed->getTimestamp()) < self::BOARDING_COOLDOWN_SECONDS
            ) {
                throw new ConflictException('BOARDING_COOLDOWN: Traveler pass consumed less than 5 minutes ago.');
            }
        }
    }
}
