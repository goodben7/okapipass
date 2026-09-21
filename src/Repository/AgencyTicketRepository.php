<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyTicket> */
class AgencyTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyTicket::class);
    }

    /**
     * Manual tickets (no booking) that still occupy a seat.
     *
     * @return list<string>
     */
    public function findActiveManualSeats(AgencyOffer $offer, \DateTimeImmutable $travelDate): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.seatNumber')
            ->andWhere('t.offer = :offer')
            ->andWhere('t.travelDate = :travelDate')
            ->andWhere('t.status != :cancelled')
            ->andWhere('t.booking IS NULL')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $travelDate)
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED);

        /** @var list<string> $seats */
        $seats = array_column($qb->getQuery()->getScalarResult(), 'seatNumber');

        return $seats;
    }

    public function findOneByReference(string $reference): ?AgencyTicket
    {
        return $this->findOneBy(['reference' => strtoupper(trim($reference))]);
    }

    public function countFutureByOffer(AgencyOffer $offer, \DateTimeImmutable $from): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.offer = :offer')
            ->andWhere('t.travelDate >= :from')
            ->andWhere('t.status != :cancelled')
            ->setParameter('offer', $offer)
            ->setParameter('from', $from)
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Tickets not yet attached to a FPT declaration, for a travel-date window.
     *
     * @return list<AgencyTicket>
     */
    public function findUndeclaredForAgencyPeriod(
        Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        /** @var list<AgencyTicket> $tickets */
        $tickets = $this->createQueryBuilder('t')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.travelDate >= :from')
            ->andWhere('t.travelDate <= :to')
            ->andWhere('t.status != :cancelled')
            ->andWhere('t.declaration IS NULL')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->orderBy('t.travelDate', 'ASC')
            ->addOrderBy('t.reference', 'ASC')
            ->getQuery()
            ->getResult();

        return $tickets;
    }
}
