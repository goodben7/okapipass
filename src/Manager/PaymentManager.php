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

        $payload = \json_decode($request->getContent(), true);

        if (!$payload) {
            $this->logger->warning('payment.flexpay.webhook.invalid_payload');
            return null;
        }

        $transactionId = $payload['transactionId'] ?? null;

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

        $incomingStatus = $payload['status'] ?? null;
        $incomingStatus = \is_string($incomingStatus) ? \strtoupper(\trim($incomingStatus)) : null;

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

        if ('SUCCESS' === $incomingStatus) {
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

                if ($check->isSuccess() && ($check->status ?? null) === 'SUCCESS') {
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
                } else {
                    if (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
                    if (Payment::STATUS_PAID !== $payment->getStatus()) {
                        $payment->setStatus(Payment::STATUS_FAILED);
                    }

                    $ticket = $payment->getTicket();
                    if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                        $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
                    }
                    }

                    $this->logger->info('payment.flexpay.webhook.not_paid_after_check', [
                        'paymentId' => $payment->getId(),
                        'transactionId' => (string) $transactionId,
                        'providerStatus' => $providerStatus,
                        'normalizedStatus' => $normalizedStatus,
                        'paymentStatus' => $payment->getStatus(),
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
        } elseif (\in_array($incomingStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR'], true)) {
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
        } else {
            $this->logger->info('payment.flexpay.webhook.ignored_unknown_status', [
                'paymentId' => $payment->getId(),
                'transactionId' => (string) $transactionId,
                'incomingStatus' => $incomingStatus,
            ]);
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
}
