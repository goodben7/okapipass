<?php

namespace App\Service;

use App\Entity\Ticket;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Twig\Environment;

class TicketPdfGenerator
{
    public function __construct(
        private readonly Environment $twig
    ) {
    }

    public function generateTicketPdf(Ticket $ticket): string
    {
        // 1. Générer le QR Code
        $data = $ticket->getUniqueReference() ?? $ticket->getId() ?? 'N/A';
        
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10
        );

        $writer = new PngWriter();
        $qrResult = $writer->write($qrCode);
        $qrBase64 = $qrResult->getDataUri();

        $issuedAt = $ticket->getIssuedAt() ?? new \DateTimeImmutable();

        // 2. Préparer le HTML avec Twig
        $html = $this->twig->render('pdf/ticket.html.twig', [
            'ticket' => $ticket,
            'qrCode' => $qrBase64,
            'issuedAt' => $issuedAt,
            'expiresAt' => $issuedAt->modify('+1 month'),
        ]);

        // 3. Configurer Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 450, 650], 'portrait'); // Taille adaptée à l'image
        $dompdf->render();

        return $dompdf->output();
    }
}
