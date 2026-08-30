<?php

namespace App\Repository;

use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyBooking> */
class AgencyBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyBooking::class);
    }

    public function findOneByPublicToken(string $publicToken): ?AgencyBooking
    {
        return $this->findOneBy([
            'publicToken' => $publicToken,
            'channel' => AgencyBooking::CHANNEL_ONLINE,
        ]);
    }

    /**
     * @return list<string>
     */
    public function findActiveSeats(AgencyOffer $offer, \DateTimeImmutable $travelDate, ?string $excludeBookingId = null): array
    {
        $now = new \DateTimeImmutable('now');

        $qb = $this->createQueryBuilder('b')
            ->select('b.seatNumber')
            ->andWhere('b.offer = :offer')
            ->andWhere('b.travelDate = :travelDate')
            ->andWhere('b.status != :cancelled')
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt >= :now')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $travelDate)
            ->setParameter('cancelled', AgencyBooking::STATUS_CANCELLED)
            ->setParameter('now', $now);

        if (null !== $excludeBookingId && '' !== $excludeBookingId) {
            $qb->andWhere('b.id != :excludeId')
                ->setParameter('excludeId', $excludeBookingId);
        }

        /** @var list<string> $seats */
        $seats = array_column($qb->getQuery()->getScalarResult(), 'seatNumber');

        return $seats;
    }

    public function countFutureByOffer(AgencyOffer $offer, \DateTimeImmutable $from): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.offer = :offer')
            ->andWhere('b.travelDate >= :from')
            ->andWhere('b.status != :cancelled')
            ->setParameter('offer', $offer)
            ->setParameter('from', $from)
            ->setParameter('cancelled', AgencyBooking::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<AgencyBooking>
     */
    public function findExpiredOnlinePending(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.channel = :online')
            ->andWhere('b.status = :pending')
            ->andWhere('b.expiresAt IS NOT NULL')
            ->andWhere('b.expiresAt < :now')
            ->andWhere('b.paymentStatus IN (:unpaidStates)')
            ->setParameter('online', AgencyBooking::CHANNEL_ONLINE)
            ->setParameter('pending', AgencyBooking::STATUS_PENDING)
            ->setParameter('now', $now)
            ->setParameter('unpaidStates', [
                AgencyBooking::PAYMENT_STATUS_UNPAID,
                AgencyBooking::PAYMENT_STATUS_PENDING,
                AgencyBooking::PAYMENT_STATUS_FAILED,
            ])
            ->getQuery()
            ->getResult();
    }
}
