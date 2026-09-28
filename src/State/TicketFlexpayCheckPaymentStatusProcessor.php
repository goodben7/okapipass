<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Manager\PaymentManager;
use App\Model\PaymentGatewayInterface;
use App\Repository\PaymentRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

class TicketFlexpayCheckPaymentStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private TicketRepository $tickets,
        private PaymentRepository $payments,
        private PaymentGatewayInterface $gateway,
        private EntityManagerInterface $em,
        private PaymentManager $paymentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Ticket
    {
        if ($data instanceof Ticket) {
            $ticket = $data;
        } else {
            $ticketId = $uriVariables['id'] ?? null;

            if (null === $ticketId || '' === (string) $ticketId) {
                return null;
            }

            $ticket = $this->tickets->find($ticketId);

            if (!$ticket instanceof Ticket) {
                return null;
            }
        }

        // Already paid → return as-is (or fingerprint winner).
        if (Ticket::PAYMENT_STATUS_PAID === $ticket->getPaymentStatus()) {
            return $ticket;
        }

        $paidSibling = $this->tickets->findRecentPaidSibling(
            $ticket,
            new \DateTimeImmutable('-120 minutes'),
        );
        if ($paidSibling instanceof Ticket) {
            return $paidSibling;
        }

        $payment = $this->payments->findOneBy(['ticket' => $ticket], ['createdAt' => 'DESC']);

        if (!$payment instanceof Payment) {
            return $ticket;
        }

        $transactionId = $payment->getProviderTransactionId();

        if (null === $transactionId || '' === \trim($transactionId)) {
            return $ticket;
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
            $winner = $this->paymentManager->applySuccessfulPayment($payment);

            return $winner instanceof Ticket ? $winner : $ticket;
        }

        if (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
            // FlexPay failed on this attempt, but a sibling may already be paid.
            $paidSibling = $this->tickets->findRecentPaidSibling(
                $ticket,
                new \DateTimeImmutable('-120 minutes'),
            );
            if ($paidSibling instanceof Ticket) {
                return $paidSibling;
            }

            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_FAILED);
            }

            if (Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
            }
            $this->em->flush();
        } else {
            $this->em->flush();
        }

        return $ticket;
    }
}
