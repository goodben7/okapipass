<?php

namespace App\Service\PublicAgency;

use App\Entity\AgencyOffer;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Entity\Checkpoint;
use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Repository\CheckpointRepository;
use App\Service\NotificationService;
use Psr\Log\LoggerInterface;

final class PublicAgencyPaymentNotifier
{
    private const string API_BASE = 'https://api.okapipass.pteron.pro';
    private const string FRONT_BASE = 'https://okapi-pass-v2.vercel.app';

    public function __construct(
        private NotificationService $notifications,
        private CheckpointRepository $checkpoints,
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
        $token = (string) ($ticket->getBooking()?->getPublicToken() ?? $ticket->getBookingGroup()?->getPublicToken() ?? '');

        $seatLine = $ticket->isGroupTicket()
            ? 'Sièges: '.implode(', ', $ticket->getGroupSeatList())
            : sprintf('Siège %s', $ticket->getSeatNumber());

        $origin = $this->resolveLocationLabel($offer instanceof AgencyOffer ? $offer->getOrigin() : null);
        $destination = $this->resolveLocationLabel($offer instanceof AgencyOffer ? $offer->getDestination() : null);

        $lines = [
            'Paiement confirmé - OkapiPass',
            ($ticket->isGroupTicket() ? 'Billet groupe: ' : 'Billet: ').$ref,
            'Nom: '.$ticket->getPassengerName(),
            sprintf('Trajet: %s → %s', $origin, $destination),
            sprintf('Date: %s — %s', $ticket->getTravelDate()?->format('d/m/Y') ?? '?', $seatLine),
            sprintf('Montant: %d %s', $total, $ticket->getCurrency()),
            'Statut: PAYÉ',
        ];

        if ('' !== $token) {
            $lines[] = "\nVoir le billet: ".self::FRONT_BASE.'/agency/booking/success?token='.$token;
        }

        $pdfPath = '' !== $token
            ? ($ticket->isGroupTicket()
                ? '/api/public/agency/booking-groups/'.$token.'/ticket/pdf'
                : '/api/public/agency/bookings/'.$token.'/ticket/pdf')
            : '';
        $pdfUrl = '' !== $pdfPath ? self::API_BASE.$pdfPath : '';

        $notification = new Notification();
        $notification->setTarget($phone);
        $notification->setTargetType(Notification::TARGET_TYPE_WHATSAPP);
        $notification->setSentVia(Notification::SENT_VIA_WHATSAPP);
        $notification->setType(NotificationType::PAYMENT_PAID);
        $notification->setTitle('OkapiPass');
        $notification->setBody(implode("\n", $lines));
        $notification->setTemplateContext([
            'reference' => $ref,
            'pdf_url' => $pdfUrl,
            'action_url' => '' !== $token
                ? self::FRONT_BASE.'/agency/booking/success?token='.$token
                : '',
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

    private function resolveLocationLabel(?string $value): string
    {
        if (null === $value || '' === trim($value)) {
            return '?';
        }

        $value = trim($value);
        if (preg_match('#(?:/api/checkpoints/)?(CP[A-Z0-9]+)$#', $value, $matches)) {
            $checkpoint = $this->checkpoints->find($matches[1]);
            if ($checkpoint instanceof Checkpoint && null !== $checkpoint->getLabel() && '' !== trim($checkpoint->getLabel())) {
                return trim($checkpoint->getLabel());
            }
        }

        return $value;
    }
}
