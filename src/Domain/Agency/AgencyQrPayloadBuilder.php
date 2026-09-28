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
     * Accepts raw qrToken (64 hex max), VP- reference, or full base64 qrPayload from scan.
     */
    public function resolveScanToken(string $raw): string
    {
        $raw = trim($raw);
        if ('' === $raw) {
            throw new UnprocessableEntityException('Empty QR / token.');
        }

        // Already a stored rotating token (bin2hex 16 bytes = 32 chars; column max 64).
        if (preg_match('/^[a-f0-9]{32,64}$/i', $raw)) {
            return strtolower($raw);
        }

        // Ticket reference pasted in the scanner (VP-…).
        if (preg_match('/^VP-/i', $raw) && null !== $this->tickets) {
            $ticket = $this->tickets->findOneByReference($raw);
            if (!$ticket instanceof AgencyTicket) {
                throw new UnavailableDataException('Ticket reference not found.');
            }
            if (null === $ticket->getQrToken()) {
                $this->refreshToken($ticket);
                $ticket->setQrPayload($this->build($ticket));
                $this->em?->flush();
            }

            return (string) $ticket->getQrToken();
        }

        // Full QR payload (base64 JSON from AgencyQrPayloadBuilder::build).
        $decoded = base64_decode($raw, true);
        if (false !== $decoded) {
            try {
                $payload = json_decode($decoded, true, 512, \JSON_THROW_ON_ERROR);
                if (\is_array($payload) && isset($payload['token']) && \is_string($payload['token']) && '' !== trim($payload['token'])) {
                    return trim($payload['token']);
                }
                if (\is_array($payload) && isset($payload['ref']) && \is_string($payload['ref']) && null !== $this->tickets) {
                    $ticket = $this->tickets->findOneByReference($payload['ref']);
                    if ($ticket instanceof AgencyTicket && null !== $ticket->getQrToken()) {
                        return (string) $ticket->getQrToken();
                    }
                }
            } catch (\JsonException) {
                // fall through
            }
        }

        // Raw JSON payload (some scanners decode base64 client-side).
        if (str_starts_with($raw, '{')) {
            try {
                $payload = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
                if (\is_array($payload) && isset($payload['token']) && \is_string($payload['token']) && '' !== trim($payload['token'])) {
                    return trim($payload['token']);
                }
            } catch (\JsonException) {
                // fall through
            }
        }

        throw new UnprocessableEntityException('Unrecognized QR payload. Scan the ticket QR or paste VP-… / token.');
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
