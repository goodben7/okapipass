<?php

namespace App\Controller\Agency;

use App\Entity\AgencyTicket;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyTicketRepository;
use App\Security\AgencyPortalAccess;
use App\Service\Agency\AgencyContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AgencyTicketInsuranceAttestationController
{
    public function __construct(
        private AgencyTicketRepository $tickets,
        private AgencyContext $agencyContext,
    ) {
    }

    #[Route(
        path: '/api/agency/tickets/{id}/insurance-attestation',
        name: 'agency_ticket_insurance_attestation',
        methods: ['GET'],
    )]
    #[IsGranted(new Expression(AgencyPortalAccess::EXPRESSION))]
    public function __invoke(string $id): Response
    {
        $ticket = $this->tickets->find($id);
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('Ticket not found.');
        }
        $this->agencyContext->assertOwns($ticket->getAgency());

        if (!$ticket->isInsuranceOpted()) {
            throw new UnprocessableEntityHttpException('Ticket has no insurance option.');
        }

        $html = sprintf(
            '<html><body style="font-family: Helvetica, sans-serif;">
            <h1>Attestation d\'assurance</h1>
            <p>Référence billet: <strong>%s</strong></p>
            <p>Passager: %s</p>
            <p>Trajet: %s → %s</p>
            <p>Date: %s</p>
            <p>Prime: %d %s</p>
            <p>Agence: %s</p>
            <p style="margin-top:2em;font-size:12px;color:#555;">Document généré le %s — OkapiPass</p>
            </body></html>',
            htmlspecialchars((string) $ticket->getReference()),
            htmlspecialchars((string) $ticket->getPassengerName()),
            htmlspecialchars((string) ($ticket->getOffer()?->getOrigin() ?? '—')),
            htmlspecialchars((string) ($ticket->getOffer()?->getDestination() ?? '—')),
            htmlspecialchars((string) ($ticket->getTravelDate()?->format('d/m/Y') ?? '—')),
            $ticket->getInsuranceFee(),
            htmlspecialchars((string) $ticket->getCurrency()),
            htmlspecialchars((string) ($ticket->getAgency()?->getName() ?? '—')),
            (new \DateTimeImmutable())->format('d/m/Y H:i'),
        );

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="assurance-'.$ticket->getReference().'.pdf"',
            'Content-Length' => (string) \strlen($pdf),
        ]);
    }
}
