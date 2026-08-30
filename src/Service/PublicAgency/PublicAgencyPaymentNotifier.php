<?php

namespace App\Service\PublicAgency;

use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Service\NotificationService;
use Psr\Log\LoggerInterface;

final class PublicAgencyPaymentNotifier
{
    public function __construct(
        private NotificationService $notifications,
        private LoggerInterface $logger,
    ) {
    }

    public function notifyPaid(AgencyPayment $payment, AgencyTicket $ticket): void
    {
        $phone = trim((string) $ticket->getPassengerPhone());
        if ('' === $phone) {
            return;
        }

        $offer = $ticket->getOffer();
        $total = $ticket->getTicketPrice() + $ticket->getPassPrice();
        $ref = (string) ($ticket->getReference() ?? $ticket->getId());
        $token = (string) ($ticket->getBooking()?->getPublicToken() ?? '');

        $lines = [
            'Paiement confirmé - OkapiPass Agence',
            'Billet: ' . $ref,
            'Passager: ' . (string) $ticket->getPassengerName(),
            sprintf('Trajet: %s → %s', $offer?->getOrigin() ?? '?', $offer?->getDestination() ?? '?'),
            sprintf('Date: %s — Siège %s', $ticket->getTravelDate()?->format('d/m/Y') ?? '?', $ticket->getSeatNumber()),
            sprintf('Montant: %d %s', $total, $ticket->getCurrency()),
        ];

        $pdfPath = '' !== $token
            ? '/api/public/agency/bookings/' . $token . '/ticket/pdf'
            : '';

        $notification = new Notification();
        $notification->setTarget($phone);
        $notification->setTargetType(Notification::TARGET_TYPE_WHATSAPP);
        $notification->setSentVia(Notification::SENT_VIA_WHATSAPP);
        $notification->setType(NotificationType::PAYMENT_PAID);
        $notification->setTitle('OkapiPass');
        $notification->setBody(implode("\n", $lines));
        $notification->setTemplateContext([
            'reference' => $ref,
            'pdf_url' => $pdfPath,
        ]);

        try {
            $this->notifications->send($notification);
        } catch (\Throwable $e) {
            $this->logger->error('agency.public.whatsapp_paid.failed', [
                'paymentId' => $payment->getId(),
                'ticketId' => $ticket->getId(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
