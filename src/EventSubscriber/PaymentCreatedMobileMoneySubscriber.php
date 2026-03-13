<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Event\ActivityEvent;
use App\Model\PaymentGatewayInterface;
use App\Service\TicketUniqueReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentCreatedMobileMoneySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PaymentGatewayInterface $gateway,
        private TicketUniqueReferenceGenerator $referenceGenerator,
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

        if (Payment::METHOD_MOBILE_MONEY !== $payment->getMethod()) {
            return;
        }

        $this->logger->info('payment.mobile_money.create_payment.start', [
            'paymentId' => $payment->getId(),
            'reference' => $payment->getReference(),
        ]);

        try {
            $response = $this->gateway->createPayment($payment);
        } catch (\Throwable $e) {
            $payment->setStatus(Payment::STATUS_FAILED);
            $this->em->flush();

            $this->logger->error('payment.mobile_money.create_payment.exception', [
                'paymentId' => $payment->getId(),
                'reference' => $payment->getReference(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($response->isSuccess()) {
            $payment->setProvider(Payment::PROVIDER_FLEXPAY);
            $payment->setProviderTransactionId($response->transactionId);
            $payment->setProviderResponse($response->raw);

            $this->logger->info('payment.mobile_money.create_payment.success', [
                'paymentId' => $payment->getId(),
                'reference' => $payment->getReference(),
                'provider' => $payment->getProvider(),
                'providerTransactionId' => $payment->getProviderTransactionId(),
                'providerStatus' => $response->status ?? null,
                'paymentStatus' => $payment->getStatus(),
                'ticketId' => $payment->getTicket()?->getId(),
                'ticketStatus' => $payment->getTicket()?->getStatus(),
                'ticketUniqueReference' => $payment->getTicket()?->getUniqueReference(),
            ]);
        } else {
            $payment->setStatus(Payment::STATUS_FAILED);

            $ticket = $payment->getTicket();
            if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
            }

            $this->logger->warning('payment.mobile_money.create_payment.failed', [
                'paymentId' => $payment->getId(),
                'reference' => $payment->getReference(),
                'providerStatus' => $response->status ?? null,
                'providerTransactionId' => $response->transactionId,
            ]);
        }

        $this->em->flush();
    }
}
