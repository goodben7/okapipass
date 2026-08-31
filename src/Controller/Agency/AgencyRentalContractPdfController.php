<?php

namespace App\Controller\Agency;

use App\Exception\UnavailableDataException;
use App\Repository\AgencyRentalContractRepository;
use App\Service\Agency\AgencyContext;
use App\Service\Agency\AgencyRentalContractPdfGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AgencyRentalContractPdfController
{
    public function __construct(
        private AgencyRentalContractRepository $contracts,
        private AgencyContext $agencyContext,
        private AgencyRentalContractPdfGenerator $pdfGenerator,
    ) {
    }

    #[Route(
        path: '/api/agency/rental-contracts/{id}/pdf',
        name: 'agency_rental_contract_pdf',
        methods: ['GET'],
    )]
    #[IsGranted('ROLE_PARTNER')]
    public function __invoke(string $id): Response
    {
        $contract = $this->contracts->find($id);
        if (null === $contract) {
            throw new UnavailableDataException('Rental contract not found.');
        }
        $this->agencyContext->assertOwns($contract->getAgency());

        $pdf = $this->pdfGenerator->generate($contract);
        $filename = sprintf('location-%s.pdf', $contract->getId());

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) \strlen($pdf),
        ]);
    }
}
