<?php

namespace App\MessageHandler;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Manager\PaymentManager;
use App\Message\CheckPaymentStatusMessage;
use App\Model\PaymentGatewayInterface;
use App\Repository\PaymentRepository;
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
            $this->reschedule($payment->getId(), $conversationPhone, $attempt, $maxAttempts);

            return;
        }

        $response = $this->gateway->checkStatus($transactionId);
        $payment->setProvider(Payment::PROVIDER_FLEXPAY);
        $payment->setProviderResponse($response->raw);

        $providerStatus = $response->status ?? null;
        $normalizedStatus = is_string($providerStatus) ? strtoupper(trim($providerStatus)) : $providerStatus;

        $ticket = $payment->getTicket();

        if ($response->isSuccess() && in_array($normalizedStatus, ['SUCCESS', 'PAID', '0', 0], true)) {
            $this->paymentManager->applySuccessfulPayment($payment, $conversationPhone);

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
        $this->reschedule($payment->getId(), $conversationPhone, $attempt, $maxAttempts);
    }

    private function reschedule(string $paymentId, string $conversationPhone, int $attempt, int $maxAttempts): void
    {
        if ($attempt >= $maxAttempts) {
            $this->logger->info('payment.check_status.max_attempts', [
                'paymentId' => $paymentId,
                'attempt' => $attempt,
            ]);

            return;
        }

        $delayMs = min(60_000, 5_000 * $attempt);
        $this->bus->dispatch(
            new CheckPaymentStatusMessage($paymentId, $conversationPhone, $attempt + 1),
            [new DelayStamp($delayMs)],
        );
    }
}
