<?php

namespace App\Manager;

use App\Contract\AgencySmsSenderInterface;
use App\Domain\Agency\AgencyNotificationTextBuilder;
use App\Entity\AgencyTicket;
use App\Entity\User;
use App\Exception\UnavailableDataException;
use App\Exception\UnauthorizedActionException;
use App\Model\UserProxyIntertace;
use App\Repository\AgencyTicketRepository;
use App\Service\Auth\TravelerOtpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class TravelerTicketManager
{
    public function __construct(
        private Security $security,
        private AgencyTicketRepository $tickets,
        private TravelerOtpService $otp,
        private AgencySmsSenderInterface $smsSender,
        private EntityManagerInterface $em,
        private AgencyNotificationTextBuilder $notificationText,
    ) {
    }

    public function requireTraveler(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedActionException('Authentication required.');
        }
        if (UserProxyIntertace::PERSON_TRAVELER !== $user->getPersonType()) {
            throw new UnauthorizedActionException('Traveler access required.');
        }

        return $user;
    }

    /**
     * @return list<AgencyTicket>
     */
    public function listTickets(?string $scope = null): array
    {
        $user = $this->requireTraveler();
        $phone = $this->otp->normalizePhone((string) $user->getPhone());

        return $this->tickets->findForTravelerPhone($phone, $scope);
    }

    public function getOwnedTicket(string $id): AgencyTicket
    {
        $user = $this->requireTraveler();
        $phone = $this->otp->normalizePhone((string) $user->getPhone());
        $ticket = $this->tickets->find($id);
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('Ticket not found.');
        }
        $ticketPhone = $this->otp->normalizePhone((string) $ticket->getPassengerPhone());
        if ($ticketPhone !== $phone) {
            throw new UnavailableDataException('Ticket not found.');
        }

        return $ticket;
    }

    /**
     * @return array{ticketId: string, toPhone: string, smsMessageId: string|null, shareUrl: string, whatsappUrl: string, shareToken: string}
     */
    public function share(string $id, string $toPhone): array
    {
        $ticket = $this->getOwnedTicket($id);
        $to = $this->otp->normalizePhone($toPhone);

        $token = bin2hex(random_bytes(16));
        $ticket->setShareToken($token);
        $ticket->setShareTokenExpiresAt(new \DateTimeImmutable('+7 days'));
        $this->em->flush();

        $shareUrl = sprintf('/api/public/tickets/share/%s', $token);
        $message = sprintf(
            'Billet %s (%s → %s) le %s. Lien: %s',
            strtoupper((string) $ticket->getReference()),
            $ticket->getOffer()?->getOrigin() ?? '—',
            $ticket->getOffer()?->getDestination() ?? '—',
            $ticket->getTravelDate()?->format('d/m/Y') ?? '—',
            $shareUrl,
        );
        $smsId = $this->smsSender->send($to, $message);
        $whatsappUrl = $this->notificationText->whatsappUrl($to, $message);

        return [
            'ticketId' => (string) $ticket->getId(),
            'toPhone' => $to,
            'smsMessageId' => $smsId,
            'shareUrl' => $shareUrl,
            'whatsappUrl' => $whatsappUrl,
            'shareToken' => $token,
        ];
    }

    public function findByShareToken(string $token): AgencyTicket
    {
        $ticket = $this->tickets->findOneBy(['shareToken' => trim($token)]);
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('Shared ticket not found.');
        }
        $expires = $ticket->getShareTokenExpiresAt();
        if ($expires instanceof \DateTimeImmutable && $expires < new \DateTimeImmutable()) {
            throw new UnavailableDataException('Share link expired.');
        }

        return $ticket;
    }
}
