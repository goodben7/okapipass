<?php

namespace App\Repository;

use App\Entity\AgencyBookingGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyBookingGroup> */
class AgencyBookingGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyBookingGroup::class);
    }

    public function findOneByPublicToken(string $publicToken): ?AgencyBookingGroup
    {
        return $this->findOneBy([
            'publicToken' => $publicToken,
            'channel' => AgencyBookingGroup::CHANNEL_ONLINE,
        ]);
    }

    /**
     * @return list<AgencyBookingGroup>
     */
    public function findExpiredOnlinePending(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.channel = :online')
            ->andWhere('g.status = :pending')
            ->andWhere('g.expiresAt IS NOT NULL')
            ->andWhere('g.expiresAt < :now')
            ->andWhere('g.paymentStatus IN (:unpaidStates)')
            ->setParameter('online', AgencyBookingGroup::CHANNEL_ONLINE)
            ->setParameter('pending', AgencyBookingGroup::STATUS_PENDING)
            ->setParameter('now', $now)
            ->setParameter('unpaidStates', [
                AgencyBookingGroup::PAYMENT_STATUS_UNPAID,
                AgencyBookingGroup::PAYMENT_STATUS_PENDING,
                AgencyBookingGroup::PAYMENT_STATUS_FAILED,
            ])
            ->getQuery()
            ->getResult();
    }
}
