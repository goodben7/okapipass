<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Model\PaymentGatewayInterface;
use App\Repository\PaymentRepository;
use App\Repository\TicketRepository;
use App\Service\TicketUniqueReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;

class TicketFlexpayCheckPaymentStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private TicketRepository $tickets,
        private PaymentRepository $payments,
        private PaymentGatewayInterface $gateway,
        private EntityManagerInterface $em,
        private TicketUniqueReferenceGenerator $referenceGenerator,
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

        $payment = $this->payments->findOneBy(['ticket' => $ticket]);

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
            $now = new \DateTimeImmutable();

            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_PAID);
            }

            if (null === $payment->getPaidAt()) {
                $payment->setPaidAt($now);
            }

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
        } elseif (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_FAILED);
            }

            if (Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
            }
        }

        $this->em->flush();

        return $ticket;
    }
}
