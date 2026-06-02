<?php

namespace App\MessageHandler;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Manager\PaymentManager;
use App\Message\CheckPaymentStatusMessage;
use App\Model\PaymentGatewayInterface;
use App\Repository\PaymentRepository;
use App\Service\TicketUniqueReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class CheckPaymentStatusMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private PaymentRepository $payments,
        private PaymentGatewayInterface $gateway,
        private TicketUniqueReferenceGenerator $referenceGenerator,
        private PaymentManager $paymentManager,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CheckPaymentStatusMessage $message): void
    {
        $payment = $this->payments->find($message->getPaymentId());
        if (!$payment instanceof Payment) {
            return;
        }

        if (Payment::METHOD_MOBILE_MONEY !== $payment->getMethod()) {
            return;
        }

        if (Payment::STATUS_PAID === $payment->getStatus()) {
            return;
        }

        $attempt = max(1, $message->getAttempt());
        $maxAttempts = 12;
        $conversationPhone = trim($message->getConversationPhone());
        if ($conversationPhone === '') {
            $conversationPhone = trim((string) ($payment->getTicket()?->getPhone() ?? ''));
        }

        $transactionId = $payment->getProviderTransactionId();
        if (null === $transactionId || '' === trim($transactionId)) {
            $this->reschedule($payment->getId(), $attempt, $maxAttempts);
            return;
        }

        $response = $this->gateway->checkStatus($transactionId);
        $payment->setProvider(Payment::PROVIDER_FLEXPAY);
        $payment->setProviderResponse($response->raw);

        $providerStatus = $response->status ?? null;
        $normalizedStatus = is_string($providerStatus) ? strtoupper(trim($providerStatus)) : $providerStatus;

        $ticket = $payment->getTicket();
        $ticketWasPaid = $ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID === $ticket->getPaymentStatus();

        if ($response->isSuccess() && in_array($normalizedStatus, ['SUCCESS', 'PAID', '0', 0], true)) {
            $now = new \DateTimeImmutable();

            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_PAID);
            }

            if (null === $payment->getPaidAt()) {
                $payment->setPaidAt($now);
            }

            if ($ticket instanceof Ticket) {
                if (Ticket::STATUS_VALIDATED !== $ticket->getStatus()) {
                    $ticket->setStatus(Ticket::STATUS_VALIDATED);
                }

                if (null === $ticket->getValidatedAt()) {
                    $ticket->setValidatedAt($now);
                }

                if (null === $ticket->getUniqueReference()) {
                    $ticket->setUniqueReference($this->referenceGenerator->generateFor($ticket));
                }

                if (Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                    $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_PAID);
                }
            }

            $this->em->flush();

        if ($ticket instanceof Ticket && !$ticketWasPaid) {
            $this->paymentManager->notifyWhatsappPaid($payment, $ticket, $conversationPhone);
            $this->em->flush();
        }

        return;
    }

    if (in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
        $ticketWasFailed = $ticket instanceof Ticket && Ticket::PAYMENT_STATUS_FAILED === $ticket->getPaymentStatus();

        if (Payment::STATUS_PAID !== $payment->getStatus()) {
            $payment->setStatus(Payment::STATUS_FAILED);
        }

        if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
            $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
        }

        $this->em->flush();

        if ($ticket instanceof Ticket && !$ticketWasFailed) {
            $this->paymentManager->notifyWhatsappFailed($payment, $ticket, $conversationPhone);
            $this->em->flush();
        }

        return;
    }

        $this->em->flush();
        $this->reschedule($payment->getId(), $attempt, $maxAttempts, $conversationPhone);
    }

    private function reschedule(?string $paymentId, int $attempt, int $maxAttempts, string $conversationPhone = ''): void
    {
        $paymentId = (string) ($paymentId ?? '');
        if ($paymentId === '' || $attempt >= $maxAttempts) {
            return;
        }

        $delayMs = min(300000, 20000 * (int) (2 ** max(0, $attempt - 1)));
        $this->bus->dispatch(
            new CheckPaymentStatusMessage($paymentId, $conversationPhone, $attempt + 1),
            [new DelayStamp($delayMs)]
        );
    }
}
