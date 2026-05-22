<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\Ticket;
use App\Enum\NotificationType;
use App\Model\PaymentGatewayInterface;
use App\Repository\PaymentRepository;
use App\Repository\TicketRepository;
use App\Service\NotificationService;
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
        private NotificationService $notifications,
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
            $ticketWasPaid = Ticket::PAYMENT_STATUS_PAID === $ticket->getPaymentStatus();

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

            if (!$ticketWasPaid) {
                $this->notifyWhatsappPaid($payment, $ticket);
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

    private function notifyWhatsappPaid(Payment $payment, Ticket $ticket): void
    {
        $phone = (string) ($ticket->getPhone() ?? '');
        $phone = trim($phone);
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

        $ref = $ticket->getUniqueReference() ?? $ticket->getId();
        $ref = (string) ($ref ?? '');

        $lines = [];
        $lines[] = 'Paiement confirmé - OkapiPass';
        if ($ref !== '') {
            $lines[] = "Pass: {$ref}";
        }
        if (null !== $ticket->getDisplayName() && '' !== trim((string) $ticket->getDisplayName())) {
            $lines[] = 'Nom: ' . trim((string) $ticket->getDisplayName());
        }
        if ($goPass instanceof \App\Entity\GoPass) {
            $lines[] = 'GoPass: ' . (string) $goPass->getLabel();
        }
        if ($departure instanceof \App\Entity\Checkpoint && $arrival instanceof \App\Entity\Checkpoint) {
            $lines[] = 'Trajet: ' . (string) $departure->getLabel() . ' → ' . (string) $arrival->getLabel();
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
        } catch (\Throwable) {
        }
    }
}
