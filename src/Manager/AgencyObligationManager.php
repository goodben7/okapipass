<?php

namespace App\Manager;

use App\Dto\Agency\BootstrapAgencyObligationsDto;
use App\Dto\Agency\CreateAgencyObligationDto;
use App\Dto\Agency\UpdateAgencyObligationDto;
use App\Entity\AgencyObligation;
use App\Entity\AgencyObligationType;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyObligationRepository;
use App\Repository\AgencyObligationTypeRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

class AgencyObligationManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyObligationRepository $obligations,
        private AgencyObligationTypeRepository $types,
    ) {
    }

    public function create(CreateAgencyObligationDto $dto): AgencyObligation
    {
        $agency = $this->agencyContext->requireAgency();
        $type = $this->resolveType($dto->type);

        $obligation = new AgencyObligation();
        $obligation->setAgency($agency);
        $obligation->setTitle((string) $dto->title);
        $obligation->setDueDate($this->parseDate((string) $dto->dueDate));
        $obligation->setType($type);
        $obligation->setReference($dto->reference);
        $obligation->setNotes($dto->notes);
        $obligation->setStatus(AgencyObligation::STATUS_OPEN);
        $obligation->setReminderDays(
            $dto->reminderDays ?? $type?->getReminderDays() ?? 30
        );

        $this->em->persist($obligation);
        $this->em->flush();

        return $obligation;
    }

    public function update(AgencyObligation $obligation, UpdateAgencyObligationDto $dto): AgencyObligation
    {
        $this->agencyContext->assertOwns($obligation->getAgency());

        if (null !== $dto->title) {
            $obligation->setTitle($dto->title);
        }
        if (null !== $dto->dueDate) {
            $obligation->setDueDate($this->parseDate($dto->dueDate));
        }
        if (null !== $dto->type) {
            $obligation->setType($this->resolveType($dto->type));
        }
        if (null !== $dto->reference) {
            $obligation->setReference($dto->reference);
        }
        if (null !== $dto->reminderDays) {
            $obligation->setReminderDays($dto->reminderDays);
        }
        if (null !== $dto->notes) {
            $obligation->setNotes($dto->notes);
        }
        if (null !== $dto->status) {
            $obligation->setStatus($dto->status);
            if (AgencyObligation::STATUS_COMPLETED === $dto->status && null === $obligation->getCompletedAt()) {
                $obligation->setCompletedAt(new \DateTimeImmutable('now'));
            }
            if (AgencyObligation::STATUS_OPEN === $dto->status) {
                $obligation->setCompletedAt(null);
            }
        }

        $this->em->flush();

        return $obligation;
    }

    public function complete(AgencyObligation $obligation): AgencyObligation
    {
        $this->agencyContext->assertOwns($obligation->getAgency());

        if (AgencyObligation::STATUS_COMPLETED === $obligation->getStatus()) {
            return $obligation;
        }
        if (AgencyObligation::STATUS_CANCELLED === $obligation->getStatus()) {
            throw new UnprocessableEntityException('Cannot complete a cancelled obligation.');
        }

        $obligation->setStatus(AgencyObligation::STATUS_COMPLETED);
        $obligation->setCompletedAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        return $obligation;
    }

    /**
     * Create missing OPEN obligations from the active catalog.
     *
     * @return list<AgencyObligation>
     */
    public function bootstrap(BootstrapAgencyObligationsDto $dto): array
    {
        $agency = $this->agencyContext->requireAgency();
        $from = null !== $dto->fromDate
            ? $this->parseDate($dto->fromDate)
            : new \DateTimeImmutable('today');

        $types = $this->types->findActiveOrdered();
        if (null !== $dto->typeCodes && [] !== $dto->typeCodes) {
            $wanted = array_map(static fn (string $c): string => strtoupper(trim($c)), $dto->typeCodes);
            $types = array_values(array_filter(
                $types,
                static fn (AgencyObligationType $t): bool => \in_array((string) $t->getCode(), $wanted, true),
            ));
        }

        $created = [];
        foreach ($types as $type) {
            if ($this->obligations->countOpenByTypeCode($agency, (string) $type->getCode()) > 0) {
                continue;
            }

            $months = $type->getDefaultValidityMonths() ?? 12;
            $due = $from->modify(sprintf('+%d months', max(1, $months)));

            $obligation = new AgencyObligation();
            $obligation->setAgency($agency);
            $obligation->setType($type);
            $obligation->setTitle((string) $type->getLabel());
            $obligation->setDueDate($due);
            $obligation->setReminderDays($type->getReminderDays());
            $obligation->setStatus(AgencyObligation::STATUS_OPEN);
            $obligation->setNotes(sprintf('Généré automatiquement (%s).', $type->getCode()));

            $this->em->persist($obligation);
            $created[] = $obligation;
        }

        $this->em->flush();

        return $created;
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     kpis: array{open: int, overdue: int, dueSoon: int, upcoming: int, completed: int},
     *     events: list<array<string, mixed>>
     * }
     */
    public function calendar(?string $fromRaw, ?string $toRaw): array
    {
        $agency = $this->agencyContext->requireAgency();
        $today = new \DateTimeImmutable('today');
        $from = null !== $fromRaw && '' !== trim($fromRaw)
            ? $this->parseDate($fromRaw)
            : $today->modify('first day of this month');
        $to = null !== $toRaw && '' !== trim($toRaw)
            ? $this->parseDate($toRaw)
            : $today->modify('last day of +2 months');

        if ($to < $from) {
            throw new UnprocessableEntityException('Query "to" must be on or after "from".');
        }

        $items = $this->obligations->findForAgencyInRange($agency, $from, $to);
        $openAll = $this->obligations->findOpenForAgency($agency);

        $overdue = 0;
        $dueSoon = 0;
        $upcoming = 0;
        foreach ($openAll as $item) {
            match ($item->getUrgency($today)) {
                AgencyObligation::URGENCY_OVERDUE => ++$overdue,
                AgencyObligation::URGENCY_DUE_SOON => ++$dueSoon,
                AgencyObligation::URGENCY_UPCOMING => ++$upcoming,
                default => null,
            };
        }

        $completedInRange = 0;
        $events = [];
        foreach ($items as $item) {
            if (AgencyObligation::STATUS_COMPLETED === $item->getStatus()) {
                ++$completedInRange;
            }
            $events[] = [
                'id' => $item->getId(),
                'title' => $item->getTitle(),
                'dueDate' => $item->getDueDate()?->format('Y-m-d'),
                'status' => $item->getStatus(),
                'urgency' => $item->getUrgency($today),
                'daysRemaining' => $item->getDaysRemaining($today),
                'reference' => $item->getReference(),
                'typeCode' => $item->getType()?->getCode(),
                'typeLabel' => $item->getType()?->getLabel(),
                'category' => $item->getType()?->getCategory(),
                'reminderDays' => $item->getReminderDays(),
            ];
        }

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'kpis' => [
                'open' => \count($openAll),
                'overdue' => $overdue,
                'dueSoon' => $dueSoon,
                'upcoming' => $upcoming,
                'completed' => $completedInRange,
            ],
            'events' => $events,
        ];
    }

    private function resolveType(?string $ref): ?AgencyObligationType
    {
        if (null === $ref || '' === trim($ref)) {
            return null;
        }

        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));
            $ref = (string) end($parts);
        }

        $type = $this->types->find($ref) ?? $this->types->findOneByCode($ref);
        if (!$type instanceof AgencyObligationType) {
            throw new UnavailableDataException(sprintf('Obligation type "%s" not found.', $ref));
        }

        return $type;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
        if (false === $parsed) {
            throw new UnprocessableEntityException('Invalid date, expected YYYY-MM-DD.');
        }

        return $parsed;
    }
}
