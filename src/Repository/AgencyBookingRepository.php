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

    /**
     * @return list<string>
     */
    public function findActiveSeats(AgencyOffer $offer, \DateTimeImmutable $travelDate, ?string $excludeBookingId = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.seatNumber')
            ->andWhere('b.offer = :offer')
            ->andWhere('b.travelDate = :travelDate')
            ->andWhere('b.status != :cancelled')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $travelDate)
            ->setParameter('cancelled', AgencyBooking::STATUS_CANCELLED);

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
}
