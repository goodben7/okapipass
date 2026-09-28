<?php

namespace App\Service;

use App\Entity\GoPass;
use App\Entity\Ticket;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Twig\Environment;

class TicketPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function generateTicketPdf(Ticket $ticket): string
    {
        $data = $ticket->getUniqueReference() ?? $ticket->getId() ?? 'N/A';

        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
        );

        $writer = new PngWriter();
        $qrBase64 = $writer->write($qrCode)->getDataUri();

        $issuedAt = $ticket->getIssuedAt() ?? new \DateTimeImmutable();
        $goPass = $ticket->getGoPass();
        $transportType = $goPass?->getTransportType();

        $html = $this->twig->render('pdf/ticket.html.twig', [
            'ticket' => $ticket,
            'qrCode' => $qrBase64,
            'issuedAt' => $issuedAt,
            'expiresAt' => $issuedAt->modify('+30 days'),
            'passLabel' => $goPass?->getLabel() ?? 'Routier',
            'transportLabel' => $this->transportLabel($transportType),
            'departureLabel' => $ticket->getDeparture()?->getLabel() ?? '—',
            'arrivalLabel' => $ticket->getArrival()?->getLabel() ?? '—',
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 450, 650], 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function transportLabel(?string $transportType): string
    {
        return match ($transportType) {
            GoPass::TRANSPORT_FLUVIAL => 'Fluvial',
            GoPass::TRANSPORT_LACUSTRE => 'Lacustre',
            default => 'Routier',
        };
    }
}
