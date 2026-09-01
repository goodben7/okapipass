<?php

namespace App\Service\Agency;

use App\Entity\AgencyTicket;
use App\Entity\Checkpoint;
use App\Repository\CheckpointRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Twig\Environment;

final class AgencyTicketPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly CheckpointRepository $checkpoints,
    ) {
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
        $transport = $offer?->getTransport();

        $html = $this->twig->render(
            $ticket->isGroupTicket() ? 'pdf/agency_group_ticket.html.twig' : 'pdf/agency_ticket.html.twig',
            [
            'ticket' => $ticket,
            'offer' => $offer,
            'agency' => $agency,
            'transport' => $transport,
            'qrCode' => $qrBase64,
            'issuedAt' => $issuedAt,
            'total' => $ticket->getTicketPrice() + $ticket->getPassPrice(),
            'companyName' => $agency?->getName() ?? 'Agence partenaire',
            'originLabel' => $this->resolveLocationLabel($offer?->getOrigin()),
            'destinationLabel' => $this->resolveLocationLabel($offer?->getDestination()),
            'routeLabel' => $offer?->getLabel(),
            'groupSeats' => $ticket->getGroupSeatList(),
            'passengerCount' => \count($ticket->getGroupSeatList()),
            'groupManifest' => $this->decodeGroupManifest($ticket),
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 450, 680], 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function resolveLocationLabel(?string $value): string
    {
        if (null === $value || '' === trim($value)) {
            return '—';
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

    /** @return list<array{seat: string, passengerName?: string|null, passengerId?: string|null, passengerPhone?: string|null}> */
    private function decodeGroupManifest(AgencyTicket $ticket): array
    {
        if (!$ticket->isGroupTicket()) {
            return [];
        }

        $notes = $ticket->getNotes();
        if (null === $notes || '' === trim($notes)) {
            return [];
        }

        try {
            $decoded = json_decode($notes, true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($decoded['groupManifest'] ?? null)) {
                return [];
            }

            return $decoded['groupManifest'];
        } catch (\Throwable) {
            return [];
        }
    }
}
