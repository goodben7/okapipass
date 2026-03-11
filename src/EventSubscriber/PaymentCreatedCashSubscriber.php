<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Event\ActivityEvent;
use App\Service\TicketUniqueReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentCreatedCashSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private TicketUniqueReferenceGenerator $referenceGenerator,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ActivityEvent::getEventName(Payment::class, Payment::EVENT_PAYMENT_CREATED) => 'onPaymentCreated',
        ];
    }

    public function onPaymentCreated(ActivityEvent $event): void
    {
        $payment = $event->getRessource();

        if (!$payment instanceof Payment) {
            return;
        }

        if (Payment::METHOD_CASH !== $payment->getMethod()) {
            return;
        }

        $ticket = $payment->getTicket();

        if (!$ticket instanceof Ticket) {
            return;
        }

        $now = new \DateTimeImmutable();

        if (Payment::STATUS_PAID !== $payment->getStatus()) {
            $payment->setStatus(Payment::STATUS_PAID);
        }

        if (null === $payment->getPaidAt()) {
            $payment->setPaidAt($now);
        }

        if (Ticket::STATUS_VALIDATED !== $ticket->getStatus()) {
            $ticket->setStatus(Ticket::STATUS_VALIDATED);
        }

        if (null === $ticket->getValidatedAt()) {
            $ticket->setValidatedAt($now);
        }

        if (null === $ticket->getUniqueReference()) {
            $ticket->setUniqueReference($this->referenceGenerator->generateFor($ticket));
        }

        $this->em->flush();
    }
}
