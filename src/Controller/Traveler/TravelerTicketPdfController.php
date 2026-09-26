<?php

namespace App\Controller\Traveler;

use App\Manager\TravelerTicketManager;
use App\Service\Agency\AgencyTicketPdfGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TravelerTicketPdfController
{
    public function __construct(
        private TravelerTicketManager $tickets,
        private AgencyTicketPdfGenerator $pdfGenerator,
    ) {
    }

    #[Route(
        path: '/api/traveler/tickets/{id}/pdf',
        name: 'traveler_ticket_pdf',
        methods: ['GET'],
    )]
    #[IsGranted('ROLE_TRAVELER')]
    public function __invoke(string $id): Response
    {
        $ticket = $this->tickets->getOwnedTicket($id);
        $pdf = $this->pdfGenerator->generate($ticket);
        $filename = sprintf('billet-%s.pdf', $ticket->getReference() ?? $ticket->getId());

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) \strlen($pdf),
        ]);
    }
}
