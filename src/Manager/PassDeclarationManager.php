<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPricingService;
use App\Domain\Agency\DeclarationCsvLimits;
use App\Domain\Agency\DeclarationCsvParser;
use App\Dto\Agency\CreatePassDeclarationDto;
use App\Dto\Agency\ImportPassDeclarationCsvDto;
use App\Entity\AgencyTicket;
use App\Entity\DeclarationLine;
use App\Entity\PassDeclaration;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyTicketRepository;
use App\Repository\PassDeclarationRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PassDeclarationManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private PassDeclarationRepository $declarations,
        private AgencyTicketRepository $tickets,
        private AgencyPricingService $pricing,
        private DeclarationCsvParser $csvParser,
    ) {
    }

    public function createManual(CreatePassDeclarationDto $dto): PassDeclaration
    {
        $agency = $this->agencyContext->requireAgency();
        $declaration = new PassDeclaration();
        $declaration->setAgency($agency);
        $declaration->setLabel((string) $dto->label);
        $declaration->setSource(PassDeclaration::SOURCE_MANUAL);
        $declaration->setStatus(PassDeclaration::STATUS_DRAFT);
        $declaration->setCurrency($dto->currency ?? $agency->getDefaultCurrency());

        foreach ($dto->lines ?? [] as $row) {
            $declaration->addLine($this->lineFromArray(\is_array($row) ? $row : []));
        }
        $declaration->recalculateFptTotal();

        $this->em->persist($declaration);
        $this->em->flush();

        return $declaration;
    }

    public function importCsv(ImportPassDeclarationCsvDto $dto): PassDeclaration
    {
        $agency = $this->agencyContext->requireAgency();
        $rows = $this->csvParser->parse($this->resolveCsvContent($dto));

        $declaration = new PassDeclaration();
        $declaration->setAgency($agency);
        $declaration->setLabel($dto->label ?: sprintf('Import CSV %s', (new \DateTimeImmutable())->format('Y-m-d H:i')));
        $declaration->setSource(PassDeclaration::SOURCE_CSV);
        $declaration->setStatus(PassDeclaration::STATUS_DRAFT);
        $declaration->setCurrency($agency->getDefaultCurrency());

        foreach ($rows as $row) {
            $declaration->addLine($this->lineFromArray($row));
        }
        $declaration->recalculateFptTotal();

        $this->em->persist($declaration);
        $this->em->flush();

        return $declaration;
    }

    /**
     * Build a draft monthly FPT declaration from undeclared tickets whose travelDate
     * falls in the given calendar month (YYYY-MM). Idempotent per agency + month.
     */
    public function generateMonthly(string $yearMonth): PassDeclaration
    {
        $agency = $this->agencyContext->requireAgency();
        $period = $this->parseYearMonth($yearMonth);

        $existing = $this->declarations->findOneMonthlyForAgency($agency, $period['yearMonth']);
        if ($existing instanceof PassDeclaration) {
            return $existing;
        }

        $tickets = $this->tickets->findUndeclaredForAgencyPeriod(
            $agency,
            $period['from'],
            $period['to'],
        );

        if ([] === $tickets) {
            throw new UnprocessableEntityException(sprintf(
                'No undeclared tickets found for period %s.',
                $period['yearMonth'],
            ));
        }

        $declaration = new PassDeclaration();
        $declaration->setAgency($agency);
        $declaration->setLabel(sprintf('FPT mensuel %s', $period['yearMonth']));
        $declaration->setSource(PassDeclaration::SOURCE_MONTHLY);
        $declaration->setStatus(PassDeclaration::STATUS_DRAFT);
        $declaration->setPeriodMonth($period['yearMonth']);
        $declaration->setCurrency($agency->getDefaultCurrency());

        foreach ($tickets as $ticket) {
            $declaration->addLine($this->lineFromTicket($ticket));
            $ticket->setDeclaration($declaration);
        }

        $declaration->recalculateFptTotal();

        $this->em->persist($declaration);
        $this->em->flush();

        return $declaration;
    }

    public function updateStatus(PassDeclaration $declaration, string $status, bool $skipOwnershipCheck = false): PassDeclaration
    {
        if (!$skipOwnershipCheck) {
            $this->agencyContext->assertOwns($declaration->getAgency());
        }
        $current = $declaration->getStatus();

        $allowed = match ($current) {
            PassDeclaration::STATUS_DRAFT => [PassDeclaration::STATUS_SUBMITTED],
            PassDeclaration::STATUS_REJECTED => [PassDeclaration::STATUS_DRAFT, PassDeclaration::STATUS_SUBMITTED],
            PassDeclaration::STATUS_SUBMITTED => [
                PassDeclaration::STATUS_VALIDATED,
                PassDeclaration::STATUS_REJECTED,
            ],
            PassDeclaration::STATUS_VALIDATED => [PassDeclaration::STATUS_PAID],
            default => [],
        };

        if (!\in_array($status, $allowed, true)) {
            throw new UnprocessableEntityException(sprintf('Cannot transition declaration from %s to %s.', $current, $status));
        }

        $now = new \DateTimeImmutable('now');
        $declaration->setStatus($status);

        if (PassDeclaration::STATUS_SUBMITTED === $status) {
            $declaration->setSubmittedAt($now);
            $declaration->setRejectionReason(null);
            $declaration->setRejectedAt(null);
        }
        if (PassDeclaration::STATUS_VALIDATED === $status) {
            $declaration->setValidatedAt($now);
            $declaration->setRejectionReason(null);
            $declaration->setRejectedAt(null);
        }
        if (PassDeclaration::STATUS_REJECTED === $status) {
            $declaration->setRejectedAt($now);
            $declaration->setValidatedAt(null);
        }
        if (PassDeclaration::STATUS_DRAFT === $status) {
            $declaration->setSubmittedAt(null);
            $declaration->setValidatedAt(null);
            $declaration->setRejectedAt(null);
            $declaration->setRejectionReason(null);
            $declaration->setPaidAt(null);
        }
        if (PassDeclaration::STATUS_PAID === $status) {
            $declaration->setPaidAt($now);
        }

        $this->em->flush();

        return $declaration;
    }

    public function validateForOnt(PassDeclaration $declaration): PassDeclaration
    {
        return $this->updateStatus($declaration, PassDeclaration::STATUS_VALIDATED, skipOwnershipCheck: true);
    }

    public function rejectForOnt(PassDeclaration $declaration, ?string $reason = null): PassDeclaration
    {
        $reason = trim((string) $reason);
        $declaration->setRejectionReason('' !== $reason ? $reason : null);

        return $this->updateStatus($declaration, PassDeclaration::STATUS_REJECTED, skipOwnershipCheck: true);
    }

    /**
     * @return array{fptDue: int, currency: string, draft: int, submitted: int, validated: int, paid: int, byCurrency: array<string, int>}
     */
    public function summary(?string $agencyId = null): array
    {
        if ($this->agencyContext->isElevated()) {
            if (null !== $agencyId && '' !== trim($agencyId)) {
                return $this->declarations->summarizeForAgency(
                    $this->agencyContext->requireAgency($agencyId)
                );
            }
            if (null !== $this->agencyContext->peekAgencyId()) {
                return $this->declarations->summarizeForAgency($this->agencyContext->requireAgency());
            }

            return $this->declarations->summarizeNationalDetailed();
        }

        return $this->declarations->summarizeForAgency($this->agencyContext->requireAgency($agencyId));
    }

    private function resolveCsvContent(ImportPassDeclarationCsvDto $dto): string
    {
        if ($dto->file instanceof UploadedFile) {
            if (!$dto->file->isValid()) {
                throw new UnprocessableEntityException('Invalid uploaded CSV file.');
            }

            $content = @file_get_contents($dto->file->getPathname());
            if (false === $content || '' === trim($content)) {
                throw new UnprocessableEntityException('Uploaded CSV file is empty.');
            }

            return $this->assertContentSize($content);
        }

        $content = trim((string) $dto->content);
        if ('' === $content) {
            throw new UnprocessableEntityException('Provide CSV via "content" or "file".');
        }

        return $this->assertContentSize($content);
    }

    private function assertContentSize(string $content): string
    {
        if (\strlen($content) > DeclarationCsvLimits::MAX_CONTENT_BYTES) {
            throw new UnprocessableEntityException(sprintf(
                'CSV content exceeds maximum size of %d bytes.',
                DeclarationCsvLimits::MAX_CONTENT_BYTES
            ));
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function lineFromArray(array $row): DeclarationLine
    {
        $passRef = isset($row['okapiPassRef']) ? (string) $row['okapiPassRef'] : null;
        $quote = $this->pricing->quote($passRef);

        $hasExisting = $row['hasExistingPass'] ?? $quote['hasExistingPass'];
        if (\is_string($hasExisting)) {
            $hasExisting = \in_array(strtolower($hasExisting), ['1', 'true', 'yes', 'oui'], true);
        }
        $hasExisting = (bool) $hasExisting;
        $passPrice = $hasExisting ? 0 : $quote['passPrice'];

        $dateRaw = (string) ($row['date'] ?? '');
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw)
            ?: \DateTimeImmutable::createFromFormat('d/m/Y', $dateRaw);
        if (false === $date) {
            throw new UnprocessableEntityException(sprintf('Invalid date "%s" in declaration line.', $dateRaw));
        }

        $line = new DeclarationLine();
        $line->setReferenceBillet((string) ($row['referenceBillet'] ?? ''));
        $line->setDate($date->setTime(0, 0));
        $line->setPassengerName((string) ($row['passengerName'] ?? ''));
        $line->setPassengerId((string) ($row['passengerId'] ?? ''));
        $line->setOrigin((string) ($row['origin'] ?? ''));
        $line->setDestination((string) ($row['destination'] ?? ''));
        $line->setTicketPrice((int) ($row['ticketPrice'] ?? 0));
        $line->setCurrency((string) ($row['currency'] ?? $quote['currency']));
        $line->setPassPrice($passPrice);
        $line->setOkapiPassRef($passRef);
        $line->setHasExistingPass($hasExisting);

        if ('' === $line->getReferenceBillet() || '' === $line->getPassengerName()) {
            throw new UnprocessableEntityException('Declaration line requires referenceBillet and passengerName.');
        }

        return $line;
    }

    private function lineFromTicket(AgencyTicket $ticket): DeclarationLine
    {
        $offer = $ticket->getOffer();
        $quote = $this->pricing->quote($ticket->getOkapiPassRef());

        $passPrice = $ticket->getPassPrice() > 0
            ? $ticket->getPassPrice()
            : ($ticket->hasExistingPass() ? 0 : (int) $quote['passPrice']);

        $line = new DeclarationLine();
        $line->setReferenceBillet((string) $ticket->getReference());
        $line->setDate($ticket->getTravelDate() ?? new \DateTimeImmutable('today'));
        $line->setPassengerName((string) $ticket->getPassengerName());
        $line->setPassengerId((string) $ticket->getPassengerId());
        $line->setOrigin((string) ($offer?->getOrigin() ?? ''));
        $line->setDestination((string) ($offer?->getDestination() ?? ''));
        $line->setTicketPrice($ticket->getTicketPrice());
        $line->setCurrency($ticket->getCurrency());
        $line->setPassPrice($passPrice);
        $line->setOkapiPassRef($ticket->getOkapiPassRef());
        $line->setHasExistingPass($ticket->hasExistingPass());

        return $line;
    }

    /**
     * @return array{yearMonth: string, from: \DateTimeImmutable, to: \DateTimeImmutable}
     */
    private function parseYearMonth(string $yearMonth): array
    {
        $yearMonth = trim($yearMonth);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
            throw new UnprocessableEntityException('yearMonth must be YYYY-MM.');
        }

        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $yearMonth.'-01');
        if (false === $from) {
            throw new UnprocessableEntityException('Invalid yearMonth.');
        }

        $to = $from->modify('last day of this month');

        return [
            'yearMonth' => $yearMonth,
            'from' => $from,
            'to' => $to,
        ];
    }
}
