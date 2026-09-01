<?php

namespace App\Controller\PublicAgency;

use App\Entity\AgencyTicket;
use App\Exception\UnavailableDataException;
use App\Manager\PublicAgencyGroupBookingManager;
use App\Service\Agency\AgencyTicketPdfGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicAgencyBookingGroupTicketPdfController
{
    public function __construct(
        private PublicAgencyGroupBookingManager $groups,
        private AgencyTicketPdfGenerator $pdfGenerator,
    ) {
    }

    #[Route(
        path: '/api/public/agency/booking-groups/{publicToken}/ticket/pdf',
        name: 'public_agency_booking_group_ticket_pdf',
        methods: ['GET'],
    )]
    public function __invoke(string $publicToken): Response
    {
        $group = $this->groups->requireOnlineGroupByToken(trim($publicToken));
        $ticket = $group->getTicket();
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('Ticket not available yet.');
        }

        $pdf = $this->pdfGenerator->generate($ticket);
        $filename = sprintf('billet-groupe-%s.pdf', $ticket->getReference() ?? $ticket->getId());

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) \strlen($pdf),
        ]);
    }
}
