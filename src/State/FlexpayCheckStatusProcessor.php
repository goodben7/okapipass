<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Model\PaymentGatewayInterface;
use App\Repository\PaymentRepository;
use App\Service\TicketUniqueReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;

class FlexpayCheckStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentRepository $payments,
        private PaymentGatewayInterface $gateway,
        private EntityManagerInterface $em,
        private TicketUniqueReferenceGenerator $referenceGenerator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Payment
    {
        if ($data instanceof Payment) {
            $payment = $data;
        } else {
            $paymentId = $uriVariables['id'] ?? null;

            if (null === $paymentId || '' === (string) $paymentId) {
                return null;
            }

            $payment = $this->payments->find($paymentId);

            if (!$payment instanceof Payment) {
                return null;
            }
        }

        $transactionId = $payment->getProviderTransactionId();

        if (null === $transactionId || '' === \trim($transactionId)) {
            return $payment;
        }

        $response = $this->gateway->checkStatus($transactionId);

        $payment->setProvider(Payment::PROVIDER_FLEXPAY);
        $payment->setProviderResponse($response->raw);

        $providerStatus = $response->status ?? null;
        $normalizedStatus = \is_string($providerStatus) ? \strtoupper(\trim($providerStatus)) : $providerStatus;

        if (
            $response->isSuccess()
            && \in_array($normalizedStatus, ['SUCCESS', 'PAID', '0', 0], true)
        ) {
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
        } elseif (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_FAILED);
            }

            $ticket = $payment->getTicket();
            if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
            }
        }

        $this->em->flush();

        return $payment;
    }
}
