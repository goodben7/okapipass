<?php

namespace App\Manager;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Entity\User;
use App\Message\Query\GetUserDetails;
use App\Message\Query\QueryBusInterface;
use App\Model\PaymentGatewayInterface;
use App\Model\NewPaymentModel;
use App\Repository\PaymentRepository;
use App\Service\ActivityEventDispatcher;
use App\Service\TicketUniqueReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PaymentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private QueryBusInterface $queries,
        private ActivityEventDispatcher $eventDispatcher,
        private RequestStack $requestStack,
        private PaymentRepository $paymentRepository,
        private TicketUniqueReferenceGenerator $referenceGenerator,
        private PaymentGatewayInterface $gateway,
        private LoggerInterface $logger,
    ) {
    }

    public function createFrom(NewPaymentModel $model): Payment
    {
        $userId = $this->security->getUser()?->getUserIdentifier();
        $paidBy = null;

        if (null !== $userId) {
            /** @var User $paidBy */
            $paidBy = $this->queries->ask(new GetUserDetails($userId));
        }

        $payment = new Payment();

        $payment->setReference($model->reference);
        $payment->setTicket($model->ticket);
        $payment->setAmount($model->amount);
        $payment->setCurrency($model->currency);
        $payment->setMethod($model->method);
        $payment->setStatus(Payment::STATUS_PENDING);
        $payment->setPaidBy($paidBy ?: null);

        $this->em->persist($payment);
        $this->em->flush();

        $this->eventDispatcher->dispatch($payment, Payment::EVENT_PAYMENT_CREATED); 



        return $payment;
    }

    public function handleWebhook(): ?Payment
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            $this->logger->warning('payment.flexpay.webhook.no_request');
            return null;
        }

        $rawBody = $request->getContent();
        $payload = \json_decode($rawBody, true);

        if (!$payload) {
            $this->logger->warning('payment.flexpay.webhook.invalid_payload', [
                'contentType' => $request->headers->get('content-type'),
                'bodySize' => \strlen($rawBody),
                'bodyPreview' => \substr($rawBody, 0, 2000),
            ]);
            return null;
        }

        $this->logger->info('payment.flexpay.webhook.body', [
            'contentType' => $request->headers->get('content-type'),
            'bodySize' => \strlen($rawBody),
            'payload' => $this->sanitizeWebhookPayload($payload),
        ]);

        $transactionId = $payload['transactionId']
            ?? $payload['orderNumber']
            ?? $payload['order_number']
            ?? $payload['reference']
            ?? ($payload['transaction']['reference'] ?? null);

        if (null === $transactionId || '' === \trim((string) $transactionId)) {
            $this->logger->warning('payment.flexpay.webhook.missing_transaction_id', [
                'payloadKeys' => \array_keys($payload),
            ]);
            return null;
        }

    
        /** @var Payment $payment */
        $payment = $this->paymentRepository->findOneBy(['providerTransactionId' => $transactionId]);

        if (!$payment) {
            $this->logger->warning('payment.flexpay.webhook.payment_not_found', [
                'transactionId' => (string) $transactionId,
            ]);
            return null;
        }

        $payment->setProviderWebhook($payload);

        $incomingStatus = $payload['status']
            ?? ($payload['transaction']['status'] ?? null)
            ?? ($payload['code'] ?? null)
            ?? ($payload['message'] ?? null);

        $incomingStatus = \is_string($incomingStatus)
            ? \strtoupper(\trim($incomingStatus))
            : (\is_int($incomingStatus) ? (string) $incomingStatus : null);

        $this->logger->info('payment.flexpay.webhook.received', [
            'paymentId' => $payment->getId(),
            'transactionId' => (string) $transactionId,
            'incomingStatus' => $incomingStatus,
        ]);

        if (Payment::STATUS_PAID === $payment->getStatus()) {
            $this->logger->info('payment.flexpay.webhook.ignored_already_paid', [
                'paymentId' => $payment->getId(),
                'transactionId' => (string) $transactionId,
            ]);
            $this->em->flush();

            return $payment;
        }

        $shouldCheck = true;

        if (\in_array($incomingStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR'], true)) {
            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_FAILED);
            }

            $ticket = $payment->getTicket();
            if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
            }

            $this->logger->info('payment.flexpay.webhook.marked_failed_from_payload', [
                'paymentId' => $payment->getId(),
                'transactionId' => (string) $transactionId,
                'incomingStatus' => $incomingStatus,
                'ticketId' => $payment->getTicket()?->getId(),
            ]);
        }

        if ($shouldCheck && Payment::STATUS_PAID !== $payment->getStatus()) {
            try {
                $check = $this->gateway->checkStatus((string) $transactionId);
                $providerWebhook = $payment->getProviderWebhook() ?? [];
                $providerWebhook['check'] = $check->raw;
                $payment->setProviderWebhook($providerWebhook);

                $providerStatus = $check->status ?? null;
                $normalizedStatus = \is_string($providerStatus) ? \strtoupper(\trim($providerStatus)) : $providerStatus;

                $this->logger->info('payment.flexpay.webhook.check_status', [
                    'paymentId' => $payment->getId(),
                    'transactionId' => (string) $transactionId,
                    'providerStatus' => $providerStatus,
                    'normalizedStatus' => $normalizedStatus,
                    'success' => $check->isSuccess(),
                ]);

                if ($check->isSuccess() && \in_array($normalizedStatus, ['SUCCESS', 'PAID', '0', 0], true)) {
                    $now = new \DateTimeImmutable();

                    if (Payment::STATUS_PAID !== $payment->getStatus()) {
                        $payment->setStatus(Payment::STATUS_PAID);
                    }

                    if (null === $payment->getPaidAt()) {
                        $payment->setPaidAt($now);
                    }

                    $ticket = $payment->getTicket();

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

                    $this->logger->info('payment.flexpay.webhook.marked_paid', [
                        'paymentId' => $payment->getId(),
                        'transactionId' => (string) $transactionId,
                        'ticketId' => $payment->getTicket()?->getId(),
                    ]);
                } elseif (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
                    if (Payment::STATUS_PAID !== $payment->getStatus()) {
                        $payment->setStatus(Payment::STATUS_FAILED);
                    }

                    $ticket = $payment->getTicket();
                    if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                        $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
                    }

                    $this->logger->info('payment.flexpay.webhook.marked_failed_after_check', [
                        'paymentId' => $payment->getId(),
                        'transactionId' => (string) $transactionId,
                        'providerStatus' => $providerStatus,
                        'normalizedStatus' => $normalizedStatus,
                    ]);
                } else {
                    $this->logger->info('payment.flexpay.webhook.left_pending_after_check', [
                        'paymentId' => $payment->getId(),
                        'transactionId' => (string) $transactionId,
                        'providerStatus' => $providerStatus,
                        'normalizedStatus' => $normalizedStatus,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('payment.flexpay.webhook.check_status.exception', [
                    'paymentId' => $payment->getId(),
                    'transactionId' => $transactionId,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->em->flush();

        $this->logger->info('payment.flexpay.webhook.done', [
            'paymentId' => $payment->getId(),
            'transactionId' => (string) $transactionId,
            'paymentStatus' => $payment->getStatus(),
            'ticketPaymentStatus' => $payment->getTicket()?->getPaymentStatus(),
        ]);

        return $payment;
    }

    private function sanitizeWebhookPayload(mixed $value): mixed
    {
        if (\is_array($value)) {
            $out = [];

            foreach ($value as $k => $v) {
                $key = \is_string($k) ? $k : (string) $k;

                if (\preg_match('/token|authorization|secret|password|passwd|bearer/i', $key)) {
                    $out[$k] = '[REDACTED]';
                    continue;
                }

                if (\preg_match('/phone|msisdn|customer/i', $key)) {
                    $digits = \is_string($v) ? \preg_replace('/\D+/', '', $v) : null;

                    if (null !== $digits && '' !== $digits) {
                        $out[$k] = '***' . \substr($digits, -3);
                    } else {
                        $out[$k] = '[REDACTED]';
                    }

                    continue;
                }

                $out[$k] = $this->sanitizeWebhookPayload($v);
            }

            return $out;
        }

        if (\is_string($value)) {
            if (\strlen($value) > 500) {
                return \substr($value, 0, 500) . '…';
            }

            return $value;
        }

        return $value;
    }
}
