<?php

namespace App\Repository;

use App\Entity\Checkpoint;
use App\Entity\GoPass;
use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * Recent unpaid ticket with the same traveler fingerprint (phone last-9 + route + GoPass).
     * Identifier is intentionally ignored — front often sends a new value per retry.
     */
    public function findReusableUnpaid(
        string $normalizedPhone,
        GoPass $goPass,
        Checkpoint $departure,
        Checkpoint $arrival,
        \DateTimeImmutable $since,
    ): ?Ticket {
        $phones = $this->phoneVariants($normalizedPhone);
        $last9 = $this->last9($normalizedPhone);

        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.goPass = :goPass')
            ->andWhere('t.departure = :departure')
            ->andWhere('t.arrival = :arrival')
            ->andWhere('t.paymentStatus IN (:pendingStatuses)')
            ->andWhere('t.status = :issued')
            ->andWhere('t.issuedAt >= :since')
            ->setParameter('goPass', $goPass)
            ->setParameter('departure', $departure)
            ->setParameter('arrival', $arrival)
            ->setParameter('pendingStatuses', [
                Ticket::PAYMENT_STATUS_PENDING,
                Ticket::PAYMENT_STATUS_FAILED,
            ])
            ->setParameter('issued', Ticket::STATUS_ISSUED)
            ->setParameter('since', $since)
            ->orderBy('t.issuedAt', 'DESC')
            ->setMaxResults(20);

        if ([] !== $phones) {
            $qb->andWhere('t.phone IN (:phones)')->setParameter('phones', $phones);
        }

        /** @var list<Ticket> $candidates */
        $candidates = $qb->getQuery()->getResult();
        foreach ($candidates as $ticket) {
            if ($this->last9((string) $ticket->getPhone()) === $last9) {
                return $ticket;
            }
        }

        // Fallback: scan recent unpaid for same route and match last-9 in PHP (legacy phone formats).
        $fallback = $this->createQueryBuilder('t')
            ->andWhere('t.goPass = :goPass')
            ->andWhere('t.departure = :departure')
            ->andWhere('t.arrival = :arrival')
            ->andWhere('t.paymentStatus IN (:pendingStatuses)')
            ->andWhere('t.status = :issued')
            ->andWhere('t.issuedAt >= :since')
            ->setParameter('goPass', $goPass)
            ->setParameter('departure', $departure)
            ->setParameter('arrival', $arrival)
            ->setParameter('pendingStatuses', [
                Ticket::PAYMENT_STATUS_PENDING,
                Ticket::PAYMENT_STATUS_FAILED,
            ])
            ->setParameter('issued', Ticket::STATUS_ISSUED)
            ->setParameter('since', $since)
            ->orderBy('t.issuedAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        foreach ($fallback as $ticket) {
            if ($ticket instanceof Ticket && $this->last9((string) $ticket->getPhone()) === $last9) {
                return $ticket;
            }
        }

        return null;
    }

    public function findRecentPaidSibling(
        Ticket $ticket,
        \DateTimeImmutable $since,
    ): ?Ticket {
        $goPass = $ticket->getGoPass();
        $departure = $ticket->getDeparture();
        $arrival = $ticket->getArrival();
        if (null === $goPass || null === $departure || null === $arrival) {
            return null;
        }

        return $this->findRecentPaidForFingerprint(
            (string) $ticket->getPhone(),
            $goPass,
            $departure,
            $arrival,
            $since,
            $ticket->getId(),
        );
    }

    public function findRecentPaidForFingerprint(
        string $phone,
        GoPass $goPass,
        Checkpoint $departure,
        Checkpoint $arrival,
        \DateTimeImmutable $since,
        ?string $excludeTicketId = null,
    ): ?Ticket {
        $last9 = $this->last9($phone);
        if ('' === $last9) {
            return null;
        }

        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.goPass = :goPass')
            ->andWhere('t.departure = :departure')
            ->andWhere('t.arrival = :arrival')
            ->andWhere('t.paymentStatus = :paid')
            ->andWhere('t.issuedAt >= :since')
            ->setParameter('goPass', $goPass)
            ->setParameter('departure', $departure)
            ->setParameter('arrival', $arrival)
            ->setParameter('paid', Ticket::PAYMENT_STATUS_PAID)
            ->setParameter('since', $since)
            ->orderBy('t.validatedAt', 'DESC')
            ->setMaxResults(30);

        if (null !== $excludeTicketId && '' !== $excludeTicketId) {
            $qb->andWhere('t.id != :id')->setParameter('id', $excludeTicketId);
        }

        /** @var list<Ticket> $rows */
        $rows = $qb->getQuery()->getResult();

        foreach ($rows as $row) {
            if ($this->last9((string) $row->getPhone()) === $last9) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<Ticket>
     */
    public function findSiblingUnpaidPending(
        Ticket $paidTicket,
        \DateTimeImmutable $since,
    ): array {
        $goPass = $paidTicket->getGoPass();
        $departure = $paidTicket->getDeparture();
        $arrival = $paidTicket->getArrival();
        $last9 = $this->last9((string) $paidTicket->getPhone());
        if ('' === $last9 || null === $goPass || null === $departure || null === $arrival) {
            return [];
        }

        /** @var list<Ticket> $rows */
        $rows = $this->createQueryBuilder('t')
            ->andWhere('t.id != :id')
            ->andWhere('t.goPass = :goPass')
            ->andWhere('t.departure = :departure')
            ->andWhere('t.arrival = :arrival')
            ->andWhere('t.paymentStatus IN (:pendingStatuses)')
            ->andWhere('t.status = :issued')
            ->andWhere('t.issuedAt >= :since')
            ->setParameter('id', $paidTicket->getId())
            ->setParameter('goPass', $goPass)
            ->setParameter('departure', $departure)
            ->setParameter('arrival', $arrival)
            ->setParameter('pendingStatuses', [
                Ticket::PAYMENT_STATUS_PENDING,
                Ticket::PAYMENT_STATUS_FAILED,
            ])
            ->setParameter('issued', Ticket::STATUS_ISSUED)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $rows,
            fn (Ticket $t): bool => $this->last9((string) $t->getPhone()) === $last9,
        ));
    }

    /** @return list<string> */
    private function phoneVariants(string $normalizedPhone): array
    {
        $digits = preg_replace('/\D+/', '', $normalizedPhone) ?? '';
        $variants = array_filter([
            $normalizedPhone,
            $digits,
            '+'.$digits,
            str_starts_with($digits, '243') ? '0'.substr($digits, 3) : null,
        ]);

        return array_values(array_unique($variants));
    }

    private function last9(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 9 ? substr($digits, -9) : $digits;
    }
}
