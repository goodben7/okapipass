<?php

namespace App\Manager;

use App\Entity\Ticket;
use App\Entity\User;
use App\Exception\UnavailableDataException;
use App\Message\Query\GetUserDetails;
use App\Message\Query\QueryBusInterface;
use App\Model\NewTicketModel;
use App\Service\ActivityEventDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class TicketManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private QueryBusInterface $queries,
        private ActivityEventDispatcher $eventDispatcher,
    )
    {
    }

    public function createFrom(NewTicketModel $model): Ticket
    {
        $userId = $this->security->getUser()?->getUserIdentifier();
        $user = null;

        if (null !== $userId) {
            /** @var User $user */
            $user = $this->queries->ask(new GetUserDetails($userId));
        }
        

        $ticket = new Ticket();

        $ticket->setDisplayName($model->displayName);
        $ticket->setPhone($model->phone);
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

    private function findTicket(string $ticketId): Ticket
    {
        $ticket = $this->em->find(Ticket::class, $ticketId);

        if (null === $ticket) {
            throw new UnavailableDataException(\sprintf('cannot find ticket with id: %s', $ticketId));
        }

        return $ticket;
    }
}
