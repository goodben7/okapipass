<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyBooking;
use App\Entity\AgencyBookingGroup;
use App\Entity\AgencyPayment;
use App\Entity\AgencyRentalContract;
use App\Entity\AgencyTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyPayment> */
class AgencyPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyPayment::class);
    }

    public function findPaidForTicket(AgencyTicket $ticket): ?AgencyPayment
    {
        return $this->findOneBy([
            'ticket' => $ticket,
            'status' => AgencyPayment::STATUS_PAID,
        ]);
    }

    public function findOpenForBooking(AgencyBooking $booking): ?AgencyPayment
    {
        return $this->findOneBy([
            'booking' => $booking,
            'status' => AgencyPayment::STATUS_PENDING,
        ]);
    }

    public function findOpenForBookingGroup(AgencyBookingGroup $group): ?AgencyPayment
    {
        return $this->findOneBy([
            'bookingGroup' => $group,
            'status' => AgencyPayment::STATUS_PENDING,
        ]);
    }

    public function findLatestForBookingGroup(AgencyBookingGroup $group): ?AgencyPayment
    {
        return $this->findOneBy(['bookingGroup' => $group], ['createdAt' => 'DESC']);
    }

    public function findOneByProviderTransactionId(string $transactionId): ?AgencyPayment
    {
        return $this->findOneBy(['providerTransactionId' => $transactionId]);
    }

    public function findLatestForBooking(AgencyBooking $booking): ?AgencyPayment
    {
        return $this->findOneBy(['booking' => $booking], ['createdAt' => 'DESC']);
    }

    public function findPaidForRentalContract(AgencyRentalContract $contract): ?AgencyPayment
    {
        return $this->findOneBy([
            'rentalContract' => $contract,
            'status' => AgencyPayment::STATUS_PAID,
        ]);
    }

    public function findOpenForRentalContract(AgencyRentalContract $contract): ?AgencyPayment
    {
        return $this->findOneBy([
            'rentalContract' => $contract,
            'status' => AgencyPayment::STATUS_PENDING,
        ]);
    }

    public function findLatestForRentalContract(AgencyRentalContract $contract): ?AgencyPayment
    {
        return $this->findOneBy(['rentalContract' => $contract], ['createdAt' => 'DESC']);
    }

    /**
     * @return list<AgencyPayment>
     */
    public function findPaidForAgencyBetween(
        Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        /** @var list<AgencyPayment> $rows */
        $rows = $this->createQueryBuilder('p')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.status = :paid')
            ->andWhere('p.paidAt >= :from')
            ->andWhere('p.paidAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('paid', AgencyPayment::STATUS_PAID)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->orderBy('p.paidAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /** @return list<AgencyPayment> */
    public function findPaidPosForAgencyBetween(
        Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        /** @var list<AgencyPayment> $rows */
        $rows = $this->createQueryBuilder('p')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.status = :paid')
            ->andWhere('p.channel = :channel')
            ->andWhere('p.paidAt >= :from')
            ->andWhere('p.paidAt <= :to')
            ->setParameter('agency', $agency)
            ->setParameter('paid', AgencyPayment::STATUS_PAID)
            ->setParameter('channel', AgencyPayment::CHANNEL_POS)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('p.paidAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /** @return list<AgencyPayment> */
    public function findPendingOlderThan(Agency $agency, \DateTimeImmutable $threshold): array
    {
        /** @var list<AgencyPayment> $rows */
        $rows = $this->createQueryBuilder('p')
            ->andWhere('p.agency = :agency')
            ->andWhere('p.status = :pending')
            ->andWhere('p.createdAt < :threshold')
            ->setParameter('agency', $agency)
            ->setParameter('pending', AgencyPayment::STATUS_PENDING)
            ->setParameter('threshold', $threshold)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
