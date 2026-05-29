<?php

namespace App\Manager;

use App\Entity\Ticket;
use App\Entity\TicketVerification;
use App\Entity\User;
use App\Message\Query\GetUserDetails;
use App\Message\Query\QueryBusInterface;
use App\Model\NewTicketVerificationModel;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class TicketVerificationManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private QueryBusInterface $queries,
        private TicketRepository $ticketRepository,
    ) {
    }

    public function createFrom(NewTicketVerificationModel $model): TicketVerification
    {
        $userId = $this->security->getUser()?->getUserIdentifier();
        $verifier = null;

        if (null !== $userId) {
            /** @var User $verifier */
            $verifier = $this->queries->ask(new GetUserDetails($userId));
        }

        if (!$verifier instanceof User) {
            throw new \RuntimeException('Verifier must be an authenticated user.');
        }

        $ticket = $this->ticketRepository->findOneBy(['uniqueReference' => $model->uniqueReference]);

        if (!$ticket instanceof Ticket) {
            throw new \RuntimeException(\sprintf('Ticket with reference "%s" not found.', $model->uniqueReference));
        }

        $verification = new TicketVerification();
        $verification->setTicket($ticket);
        $verification->setCheckpoint($model->checkpoint);
        $verification->setVerifiedBy($verifier);
        $verification->setComment($model->comment);

        if ($ticket->getArrival() && $ticket->getArrival()->getId() === $model->checkpoint->getId()) {
            $ticket->setStatus(Ticket::STATUS_ARRIVED);
        }

        $this->em->persist($verification);
        $this->em->flush();

        return $verification;
    }
}
