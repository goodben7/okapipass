<?php

namespace App\Controller\PublicAgency;

use App\Manager\TravelerTicketManager;
use App\Provider\Traveler\TravelerTicketCollectionProvider;
use App\Service\Agency\AgencyTicketPdfGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicSharedTicketController
{
    public function __construct(
        private TravelerTicketManager $tickets,
        private TravelerTicketCollectionProvider $mapper,
        private AgencyTicketPdfGenerator $pdfGenerator,
    ) {
    }

    #[Route(
        path: '/api/public/tickets/share/{token}',
        name: 'public_shared_ticket',
        methods: ['GET'],
    )]
    public function view(string $token): JsonResponse
    {
        $ticket = $this->tickets->findByShareToken($token);
        $resource = $this->mapper->map($ticket);
        $resource->pdfUrl = sprintf('/api/public/tickets/share/%s/pdf', $token);

        return new JsonResponse([
            'id' => $resource->id,
            'reference' => $resource->reference,
            'passengerName' => $resource->passengerName,
            'passengerPhone' => $resource->passengerPhone,
            'seatNumber' => $resource->seatNumber,
            'travelDate' => $resource->travelDate,
            'status' => $resource->status,
            'ticketPrice' => $resource->ticketPrice,
            'passPrice' => $resource->passPrice,
            'discountAmount' => $resource->discountAmount,
            'promoCode' => $resource->promoCode,
            'currency' => $resource->currency,
            'agency' => $resource->agency,
            'offer' => $resource->offer,
            'pdfUrl' => $resource->pdfUrl,
        ]);
    }

    #[Route(
        path: '/api/public/tickets/share/{token}/pdf',
        name: 'public_shared_ticket_pdf',
        methods: ['GET'],
    )]
    public function pdf(string $token): Response
    {
        $ticket = $this->tickets->findByShareToken($token);
        $pdf = $this->pdfGenerator->generate($ticket);
        $filename = sprintf('billet-%s.pdf', $ticket->getReference() ?? $ticket->getId());

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) \strlen($pdf),
        ]);
    }
}
