<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use App\Event\ActivityEvent;
use App\Manager\PaymentManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentCreatedCashSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaymentManager $paymentManager,
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

        if (Payment::METHOD_CASH !== $payment->getMethod()) {
            return;
        }

        $this->paymentManager->applySuccessfulPayment($payment);
    }
}
