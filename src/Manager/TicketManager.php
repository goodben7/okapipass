<?php

namespace App\Manager;

use App\Entity\Ticket;
use App\Entity\User;
use App\Exception\UnavailableDataException;
use App\Message\Query\GetUserDetails;
use App\Message\Query\QueryBusInterface;
use App\Model\NewTicketModel;
use App\Repository\TicketRepository;
use App\Service\ActivityEventDispatcher;
use App\Service\Auth\TravelerOtpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class TicketManager
{
    private const int REUSE_WINDOW_MINUTES = 45;

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private QueryBusInterface $queries,
        private ActivityEventDispatcher $eventDispatcher,
        private TicketRepository $tickets,
        private TravelerOtpService $otp,
    ) {
    }

    public function createFrom(NewTicketModel $model): Ticket
    {
        $rawPhone = trim((string) ($model->phone ?? ''));
        $phone = '' !== $rawPhone ? $this->softNormalizePhone($rawPhone) : '';

        $conn = $this->em->getConnection();
        if ('' !== $phone && null !== $model->goPass && null !== $model->departure && null !== $model->arrival) {
            $lockName = 'okp_tkt_'.md5(implode('|', [
                $this->last9($phone),
                (string) $model->goPass->getId(),
                (string) $model->departure->getId(),
                (string) $model->arrival->getId(),
            ]));
            $conn->executeQuery('SELECT GET_LOCK(?, 8)', [$lockName]);

            try {
                $since = new \DateTimeImmutable(sprintf('-%d minutes', self::REUSE_WINDOW_MINUTES));

                $alreadyPaid = $this->tickets->findRecentPaidForFingerprint(
                    $phone,
                    $model->goPass,
                    $model->departure,
                    $model->arrival,
                    $since,
                );
                if ($alreadyPaid instanceof Ticket) {
                    return $alreadyPaid;
                }

                $existing = $this->tickets->findReusableUnpaid(
                    $phone,
                    $model->goPass,
                    $model->departure,
                    $model->arrival,
                    $since,
                );
                if ($existing instanceof Ticket) {
                    return $existing;
                }

                return $this->persistNewTicket($model, $phone);
            } finally {
                $conn->executeQuery('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }

        return $this->persistNewTicket($model, $phone !== '' ? $phone : $model->phone);
    }

    private function persistNewTicket(NewTicketModel $model, ?string $phone): Ticket
    {
        $userId = $this->security->getUser()?->getUserIdentifier();
        $user = null;

        if (null !== $userId) {
            /** @var User $user */
            $user = $this->queries->ask(new GetUserDetails($userId));
        }

        $ticket = new Ticket();

        $ticket->setDisplayName($model->displayName);
        $ticket->setPhone($phone);
        $ticket->setIdentifier($model->identifier);
        $ticket->setGoPass($model->goPass);
        $ticket->setDeparture($model->departure);
        $ticket->setArrival($model->arrival);
        $ticket->setIssuedBy($user ?: null);
        $ticket->setStatus(Ticket::STATUS_ISSUED);

        $this->em->persist($ticket);
        $this->em->flush();

        $this->eventDispatcher->dispatch($ticket, Ticket::EVENT_TICKET_CREATED, null, $model->method);

        return $ticket;
    }

    private function softNormalizePhone(string $raw): string
    {
        try {
            return $this->otp->normalizePhone($raw);
        } catch (\Throwable) {
            $digits = preg_replace('/\D+/', '', $raw) ?? '';
            if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
                return '+243'.substr($digits, 1);
            }
            if (str_starts_with($digits, '243')) {
                return '+'.$digits;
            }

            return '' !== $digits ? $digits : $raw;
        }
    }

    private function last9(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 9 ? substr($digits, -9) : $digits;
    }

    private function findTicket(string $ticketId): Ticket
    {
        $ticket = $this->em->find(Ticket::class, $ticketId);

        if (null === $ticket) {
            throw new UnavailableDataException(\sprintf('cannot find ticket with id: %s', $ticketId));
        }

        return $ticket;
    }
}
