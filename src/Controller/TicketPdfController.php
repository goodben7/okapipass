<?php

namespace App\Controller;

use App\Repository\TicketRepository;
use App\Service\TicketPdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TicketPdfController extends AbstractController
{
    #[Route('/api/tickets/{id}/download-pdf', name: 'app_ticket_pdf_download', methods: ['GET'])]
    public function download(string $id, TicketRepository $ticketRepository, TicketPdfGenerator $pdfGenerator): Response
    {
        $ticket = $ticketRepository->find($id);
        if (!$ticket) {
            throw $this->createNotFoundException('Ticket non trouvé');
        }

        $pdfContent = $pdfGenerator->generateTicketPdf($ticket);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ticket-' . ($ticket->getUniqueReference() ?? $id) . '.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }
}
