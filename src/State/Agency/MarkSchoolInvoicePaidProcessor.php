<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\MarkSchoolInvoicePaidDto;
use App\Entity\SchoolInvoice;
use App\Exception\UnavailableDataException;
use App\Manager\SchoolInvoiceManager;
use App\Repository\SchoolInvoiceRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<MarkSchoolInvoicePaidDto, SchoolInvoice> */
final class MarkSchoolInvoicePaidProcessor implements ProcessorInterface
{
    public function __construct(
        private SchoolInvoiceManager $manager,
        private SchoolInvoiceRepository $invoices,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolInvoice
    {
        \assert($data instanceof MarkSchoolInvoicePaidDto);

        return $this->manager->markPaid($this->requireInvoice($uriVariables), $data);
    }

    /** @param array<string, mixed> $uriVariables */
    private function requireInvoice(array $uriVariables): SchoolInvoice
    {
        $invoice = $this->invoices->find($uriVariables['id'] ?? null);
        if (!$invoice instanceof SchoolInvoice) {
            throw new UnavailableDataException('School invoice not found.');
        }
        $this->agencyContext->assertOwns($invoice->getAgency());

        return $invoice;
    }
}
