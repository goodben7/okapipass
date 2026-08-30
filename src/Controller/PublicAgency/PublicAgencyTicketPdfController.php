<?php

namespace App\Controller\PublicAgency;

use App\Entity\AgencyBooking;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyBookingRepository;
use App\Service\Agency\AgencyTicketPdfGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicAgencyTicketPdfController
{
    public function __construct(
        private AgencyBookingRepository $bookings,
        private AgencyTicketPdfGenerator $pdfGenerator,
    ) {
    }

    #[Route(
        path: '/api/public/agency/bookings/{publicToken}/ticket/pdf',
        name: 'public_agency_ticket_pdf',
        methods: ['GET'],
    )]
    public function __invoke(string $publicToken): Response
    {
        $booking = $this->bookings->findOneByPublicToken(trim($publicToken));
        if (!$booking instanceof AgencyBooking || AgencyBooking::CHANNEL_ONLINE !== $booking->getChannel()) {
            throw new UnavailableDataException('Booking not found.');
        }

        $ticket = $booking->getTicket();
        if (null === $ticket) {
            throw new UnavailableDataException('Ticket not available yet.');
        }

        $pdf = $this->pdfGenerator->generate($ticket);
        $filename = sprintf('billet-%s.pdf', $ticket->getReference() ?? $ticket->getId());

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) \strlen($pdf),
        ]);
    }
}
