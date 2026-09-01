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
        $total = $ticket->getTicketPrice() + $ticket->getPassPrice();
        $ref = (string) ($ticket->getReference() ?? $ticket->getId());
        $token = (string) ($ticket->getBooking()?->getPublicToken() ?? $ticket->getBookingGroup()?->getPublicToken() ?? '');

        $seatLine = $ticket->isGroupTicket()
            ? 'Sièges: '.implode(', ', $ticket->getGroupSeatList())
            : sprintf('Siège %s', $ticket->getSeatNumber());

        $lines = [
            'Paiement confirmé - OkapiPass Agence',
            ($ticket->isGroupTicket() ? 'Billet groupe: ' : 'Billet: ').$ref,
            'Groupe: '.($ticket->isGroupTicket() ? $ticket->getPassengerName() : $ticket->getPassengerName()),
            sprintf('Trajet: %s → %s', $offer?->getOrigin() ?? '?', $offer?->getDestination() ?? '?'),
            sprintf('Date: %s — %s', $ticket->getTravelDate()?->format('d/m/Y') ?? '?', $seatLine),
            sprintf('Montant: %d %s', $total, $ticket->getCurrency()),
        ];

        $pdfPath = '' !== $token
            ? ($ticket->isGroupTicket()
                ? '/api/public/agency/booking-groups/'.$token.'/ticket/pdf'
                : '/api/public/agency/bookings/'.$token.'/ticket/pdf')
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
