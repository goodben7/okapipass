<?php

namespace App\MessageHandler;

use App\Entity\Checkpoint;
use App\Entity\GoPass;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Enum\NotificationType;
use App\Message\CheckPaymentStatusMessage;
use App\Model\PaymentGatewayInterface;
use App\Repository\PaymentRepository;
use App\Service\NotificationService;
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
        private NotificationService $notifications,
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
                $this->sendPaidWhatsappMessage($payment, $ticket, $conversationPhone);
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
                $this->sendFailedWhatsappMessage($payment, $ticket, $conversationPhone);
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

    private function sendPaidWhatsappMessage(Payment $payment, Ticket $ticket, string $conversationPhone): void
    {
        $phone = trim($conversationPhone);
        if ($phone === '') {
            return;
        }

        $webhook = $payment->getProviderWebhook();
        $webhook = is_array($webhook) ? $webhook : [];
        $meta = $webhook['_okapi'] ?? null;
        $meta = is_array($meta) ? $meta : [];
        if (($meta['whatsapp_paid_notified'] ?? false) === true) {
            return;
        }

        $goPass = $ticket->getGoPass();
        $departure = $ticket->getDeparture();
        $arrival = $ticket->getArrival();

        $amount = (string) ($payment->getAmount() ?? '');
        $currency = (string) ($payment->getCurrency() ?? '');

        $ref = (string) ($ticket->getUniqueReference() ?? $ticket->getId() ?? '');
        $displayName = trim((string) ($ticket->getDisplayName() ?? ''));

        $lines = [];
        $lines[] = 'Paiement confirmé - OkapiPass';
        if ($ref !== '') {
            $lines[] = "Pass: {$ref}";
        }
        if ($displayName !== '') {
            $lines[] = "Nom: {$displayName}";
        }
        if ($goPass instanceof GoPass) {
            $lines[] = "GoPass: {$goPass->getLabel()}";
        }
        if ($departure instanceof Checkpoint && $arrival instanceof Checkpoint) {
            $lines[] = "Trajet: {$departure->getLabel()} → {$arrival->getLabel()}";
        }
        if ($amount !== '' && $currency !== '') {
            $lines[] = "Montant: {$amount} {$currency}";
        }
        $lines[] = 'Statut: PAYÉ';
        $lines[] = "\nLien de votre pass : https://okapi-pass-v2.vercel.app/payment/success?ref=" . $ref;

        $notification = new Notification();
        $notification->setTarget($phone);
        $notification->setTargetType(Notification::TARGET_TYPE_WHATSAPP);
        $notification->setSentVia(Notification::SENT_VIA_WHATSAPP);
        $notification->setType(NotificationType::PAYMENT_PAID);
        $notification->setTitle('OkapiPass');
        $notification->setBody(implode("\n", $lines));

        try {
            $this->notifications->send($notification);
            $meta['whatsapp_paid_notified'] = true;
            $webhook['_okapi'] = $meta;
            $payment->setProviderWebhook($webhook);
        } catch (\Throwable $e) {
            $this->logger->error('payment.whatsapp_paid_notification.failed', [
                'paymentId' => $payment->getId(),
                'ticketId' => $ticket->getId(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function sendFailedWhatsappMessage(Payment $payment, Ticket $ticket, string $conversationPhone): void
    {
        $phone = trim($conversationPhone);
        if ($phone === '') {
            return;
        }

        $webhook = $payment->getProviderWebhook();
        $webhook = is_array($webhook) ? $webhook : [];
        $meta = $webhook['_okapi'] ?? null;
        $meta = is_array($meta) ? $meta : [];
        if (($meta['whatsapp_failed_notified'] ?? false) === true) {
            return;
        }

        $goPass = $ticket->getGoPass();
        $departure = $ticket->getDeparture();
        $arrival = $ticket->getArrival();

        $amount = (string) ($payment->getAmount() ?? '');
        $currency = (string) ($payment->getCurrency() ?? '');

        $ref = (string) ($ticket->getUniqueReference() ?? $ticket->getId() ?? '');
        $displayName = trim((string) ($ticket->getDisplayName() ?? ''));

        $lines = [];
        $lines[] = 'Paiement échoué - OkapiPass';
        if ($ref !== '') {
            $lines[] = "Pass: {$ref}";
        }
        if ($displayName !== '') {
            $lines[] = "Nom: {$displayName}";
        }
        if ($goPass instanceof GoPass) {
            $lines[] = "GoPass: {$goPass->getLabel()}";
        }
        if ($departure instanceof Checkpoint && $arrival instanceof Checkpoint) {
            $lines[] = "Trajet: {$departure->getLabel()} → {$arrival->getLabel()}";
        }
        if ($amount !== '' && $currency !== '') {
            $lines[] = "Montant: {$amount} {$currency}";
        }
        $lines[] = 'Statut: ÉCHOUÉ';
        $lines[] = 'Tape MENU pour recommencer.';

        $notification = new Notification();
        $notification->setTarget($phone);
        $notification->setTargetType(Notification::TARGET_TYPE_WHATSAPP);
        $notification->setSentVia(Notification::SENT_VIA_WHATSAPP);
        $notification->setType(NotificationType::PAYMENT_FAILED);
        $notification->setTitle('OkapiPass');
        $notification->setBody(implode("\n", $lines));

        try {
            $this->notifications->send($notification);
            $meta['whatsapp_failed_notified'] = true;
            $webhook['_okapi'] = $meta;
            $payment->setProviderWebhook($webhook);
        } catch (\Throwable $e) {
            $this->logger->error('payment.whatsapp_failed_notification.failed', [
                'paymentId' => $payment->getId(),
                'ticketId' => $ticket->getId(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
