<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Event\ActivityEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentCreatedCardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
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

        if (Payment::METHOD_CARD !== $payment->getMethod()) {
            return;
        }

        $this->logger->info('payment.card.create_payment.start', [
            'paymentId' => $payment->getId(),
            'reference' => $payment->getReference(),
        ]);

        $payment->setProvider(Payment::PROVIDER_FLEXPAY);
        $payment->setProviderResponse([
            'mode' => 'HTML_FORM',
            'formUrl' => \sprintf('/api/payments/%s/card/form', (string) $payment->getId()),
        ]);

        if (Payment::STATUS_PENDING !== $payment->getStatus()) {
            $payment->setStatus(Payment::STATUS_PENDING);
        }

        $ticket = $payment->getTicket();
        if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PENDING !== $ticket->getPaymentStatus()) {
            $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_PENDING);
        }

        $this->logger->info('payment.card.create_payment.html_form_ready', [
            'paymentId' => $payment->getId(),
            'reference' => $payment->getReference(),
            'formUrl' => \sprintf('/api/payments/%s/card/form', (string) $payment->getId()),
        ]);

        $this->em->flush();
    }
}
