<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Manager\PaymentManager;
use App\Model\PaymentGatewayInterface;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;

class FlexpayCheckStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentRepository $payments,
        private PaymentGatewayInterface $gateway,
        private EntityManagerInterface $em,
        private PaymentManager $paymentManager,
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

        if (Payment::STATUS_PAID === $payment->getStatus()) {
            $ticket = $payment->getTicket();
            if ($ticket instanceof Ticket) {
                $this->paymentManager->notifyWhatsappPaid($payment, $ticket);
                $this->em->flush();
            }

            return $payment;
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
            $this->paymentManager->applySuccessfulPayment($payment);
        } elseif (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
            if (Payment::STATUS_PAID !== $payment->getStatus()) {
                $payment->setStatus(Payment::STATUS_FAILED);
            }

            $ticket = $payment->getTicket();
            if ($ticket instanceof Ticket && Ticket::PAYMENT_STATUS_PAID !== $ticket->getPaymentStatus()) {
                $ticket->setPaymentStatus(Ticket::PAYMENT_STATUS_FAILED);
            }
            $this->em->flush();
        } else {
            $this->em->flush();
        }

        return $payment;
    }
}
