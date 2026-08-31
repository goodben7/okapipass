<?php

namespace App\Service\Agency;

use App\Entity\AgencyRentalContract;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class AgencyRentalContractPdfGenerator
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function generate(AgencyRentalContract $contract): string
    {
        $html = $this->twig->render('pdf/agency_rental_contract.html.twig', [
            'contract' => $contract,
            'agency' => $contract->getAgency(),
            'transport' => $contract->getTransport(),
            'driver' => $contract->getDriver(),
            'issuedAt' => new \DateTimeImmutable('now'),
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
