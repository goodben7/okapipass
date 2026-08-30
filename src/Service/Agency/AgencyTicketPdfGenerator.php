<?php

namespace App\Service\Agency;

use App\Entity\AgencyTicket;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Twig\Environment;

final class AgencyTicketPdfGenerator
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function generate(AgencyTicket $ticket): string
    {
        $qrPayload = (string) ($ticket->getQrPayload() ?? $ticket->getReference() ?? $ticket->getId());

        $qrCode = new QrCode(
            data: $qrPayload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
        );

        $writer = new PngWriter();
        $qrBase64 = $writer->write($qrCode)->getDataUri();

        $offer = $ticket->getOffer();
        $agency = $ticket->getAgency();
        $issuedAt = $ticket->getCreatedAt() ?? new \DateTimeImmutable();

        $html = $this->twig->render('pdf/agency_ticket.html.twig', [
            'ticket' => $ticket,
            'offer' => $offer,
            'agency' => $agency,
            'qrCode' => $qrBase64,
            'issuedAt' => $issuedAt,
            'total' => $ticket->getTicketPrice() + $ticket->getPassPrice(),
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 450, 650], 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
