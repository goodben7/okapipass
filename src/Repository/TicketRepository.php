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
     * Recent unpaid ticket with the same traveler fingerprint (retries / new Idempotency-Key).
     */
    public function findReusableUnpaid(
        string $phone,
        GoPass $goPass,
        Checkpoint $departure,
        Checkpoint $arrival,
        ?string $identifier,
        \DateTimeImmutable $since,
    ): ?Ticket {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.phone = :phone')
            ->andWhere('t.goPass = :goPass')
            ->andWhere('t.departure = :departure')
            ->andWhere('t.arrival = :arrival')
            ->andWhere('t.paymentStatus IN (:pendingStatuses)')
            ->andWhere('t.status = :issued')
            ->andWhere('t.issuedAt >= :since')
            ->setParameter('phone', $phone)
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
            ->setMaxResults(1);

        if (null === $identifier || '' === trim($identifier)) {
            $qb->andWhere('t.identifier IS NULL OR t.identifier = :emptyId')
                ->setParameter('emptyId', '');
        } else {
            $qb->andWhere('t.identifier = :identifier')
                ->setParameter('identifier', $identifier);
        }

        $ticket = $qb->getQuery()->getOneOrNullResult();

        return $ticket instanceof Ticket ? $ticket : null;
    }

    /**
     * @return list<Ticket>
     */
    public function findSiblingUnpaidPending(
        Ticket $paidTicket,
        \DateTimeImmutable $since,
    ): array {
        $phone = trim((string) $paidTicket->getPhone());
        $goPass = $paidTicket->getGoPass();
        $departure = $paidTicket->getDeparture();
        $arrival = $paidTicket->getArrival();
        if ('' === $phone || null === $goPass || null === $departure || null === $arrival) {
            return [];
        }

        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.id != :id')
            ->andWhere('t.phone = :phone')
            ->andWhere('t.goPass = :goPass')
            ->andWhere('t.departure = :departure')
            ->andWhere('t.arrival = :arrival')
            ->andWhere('t.paymentStatus = :pending')
            ->andWhere('t.status = :issued')
            ->andWhere('t.issuedAt >= :since')
            ->setParameter('id', $paidTicket->getId())
            ->setParameter('phone', $phone)
            ->setParameter('goPass', $goPass)
            ->setParameter('departure', $departure)
            ->setParameter('arrival', $arrival)
            ->setParameter('pending', Ticket::PAYMENT_STATUS_PENDING)
            ->setParameter('issued', Ticket::STATUS_ISSUED)
            ->setParameter('since', $since);

        /** @var list<Ticket> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }
}
