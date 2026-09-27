<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\GenerateSchoolInvoiceDto;
use App\Dto\Agency\MarkSchoolInvoicePaidDto;
use App\Entity\SchoolContract;
use App\Entity\SchoolInvoice;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\SchoolContractRepository;
use App\Repository\SchoolInvoiceRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class SchoolInvoiceManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private SchoolContractRepository $contracts,
        private SchoolInvoiceRepository $invoices,
        private AccountingAgencyManager $accounting,
    ) {
    }

    public function generate(string $contractId, GenerateSchoolInvoiceDto $dto): SchoolInvoice
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $contract = $this->contracts->find($contractId);
        if (!$contract instanceof SchoolContract || $contract->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('School contract not found.');
        }

        $periodYm = (string) $dto->periodYm;
        $existing = $this->invoices->findOneByContractAndPeriod($contract, $periodYm);
        if ($existing instanceof SchoolInvoice) {
            // Idempotent: return existing invoice for the period.
            return $existing;
        }

        $amount = $contract->getMonthlyFee();
        if ($amount <= 0) {
            throw new UnprocessableEntityException('Contract monthlyFee must be greater than 0 to generate an invoice.');
        }

        $invoice = new SchoolInvoice();
        $invoice->setAgency($agency);
        $invoice->setContract($contract);
        $invoice->setPeriodYm($periodYm);
        $invoice->setAmount($amount);
        $invoice->setCurrency($contract->getCurrency());
        $invoice->setStatus(SchoolInvoice::STATUS_ISSUED);
        $invoice->setIssuedAt(new \DateTimeImmutable('now'));

        $this->em->persist($invoice);
        $this->em->flush();

        return $invoice;
    }

    public function markPaid(SchoolInvoice $invoice, MarkSchoolInvoicePaidDto $dto): SchoolInvoice
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $this->agencyContext->assertOwns($invoice->getAgency());

        if (SchoolInvoice::STATUS_CANCELLED === $invoice->getStatus()) {
            throw new UnprocessableEntityException('Cannot mark a cancelled invoice as paid.');
        }
        if (SchoolInvoice::STATUS_PAID === $invoice->getStatus()) {
            return $invoice;
        }
        if (!\in_array($invoice->getStatus(), [SchoolInvoice::STATUS_DRAFT, SchoolInvoice::STATUS_ISSUED], true)) {
            throw new ConflictException(sprintf('Invoice status %s cannot be marked paid.', $invoice->getStatus()));
        }

        $invoice->setStatus(SchoolInvoice::STATUS_PAID);
        $invoice->setPaidAt(new \DateTimeImmutable('now'));
        if (null !== $dto->notes && '' !== trim($dto->notes)) {
            $existingNotes = $invoice->getNotes();
            $invoice->setNotes(
                null !== $existingNotes && '' !== $existingNotes
                    ? $existingNotes."\n".$dto->notes
                    : $dto->notes
            );
        }

        $this->em->flush();
        $this->accounting->recordSchoolInvoicePaid($invoice);

        return $invoice;
    }

    public function cancel(SchoolInvoice $invoice): SchoolInvoice
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $this->agencyContext->assertOwns($invoice->getAgency());

        if (SchoolInvoice::STATUS_PAID === $invoice->getStatus()) {
            throw new UnprocessableEntityException('Cannot cancel a paid invoice.');
        }
        if (SchoolInvoice::STATUS_CANCELLED === $invoice->getStatus()) {
            return $invoice;
        }

        $invoice->setStatus(SchoolInvoice::STATUS_CANCELLED);
        $this->em->flush();

        return $invoice;
    }
}
