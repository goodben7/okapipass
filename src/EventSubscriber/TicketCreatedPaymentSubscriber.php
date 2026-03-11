<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Event\ActivityEvent;
use App\Manager\PaymentManager;
use App\Model\NewPaymentModel;
use App\Repository\PaymentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TicketCreatedPaymentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaymentRepository $payments,
        private PaymentManager $manager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ActivityEvent::getEventName(Ticket::class, Ticket::EVENT_TICKET_CREATED) => 'onTicketCreated',
        ];
    }

    public function onTicketCreated(ActivityEvent $event): void
    {
        $ticket = $event->getRessource();

        if (!$ticket instanceof Ticket) {
            return;
        }

        $existing = $this->payments->findOneBy(['ticket' => $ticket]);

        if (null !== $existing) {
            return;
        }

        $goPass = $ticket->getGoPass();
        $amount = null;
        $currency = null;

        if (null !== $goPass) {
            $amount = \number_format((float) $goPass->getPrice(), 2, '.', '');
            $currency = $goPass->getCurrency();
        }

        if (null === $amount || null === $currency) {
            return;
        }

        $method = $event->getActivityDescription();

        if (null === $method || !\in_array($method, Payment::getMethodsAsList(), true)) {
            $method = Payment::METHOD_CASH;
        }

        $ticketId = (string) $ticket->getId();
        $identifier = $ticket->getIdentifier();

        if (null !== $identifier && '' !== \trim($identifier)) {
            $reference = \substr(\sprintf('PAY-%s-%s', $identifier, $ticketId), 0, 80);
        } else {
            $reference = \substr(\sprintf('PAY-%s', $ticketId), 0, 80);
        }

        $this->manager->createFrom(new NewPaymentModel(
            $reference,
            $ticket,
            $amount,
            $currency,
            $method,
        ));
    }
}
