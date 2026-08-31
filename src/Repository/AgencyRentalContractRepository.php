<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyRentalContract;
use App\Entity\AgencyTransport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgencyRentalContract>
 */
class AgencyRentalContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyRentalContract::class);
    }

    /** @return list<string> */
    public static function blockingStatuses(): array
    {
        return [
            AgencyRentalContract::STATUS_CONFIRMED,
            AgencyRentalContract::STATUS_ACTIVE,
        ];
    }

    public function hasBlockingRentalOnTravelDate(AgencyTransport $transport, \DateTimeImmutable $travelDate): bool
    {
        $dayStart = $travelDate->setTime(0, 0);
        $dayEnd = $travelDate->setTime(23, 59, 59);

        return $this->hasOverlap($transport, $dayStart, $dayEnd);
    }

    public function hasOverlap(
        AgencyTransport $transport,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        ?string $excludeContractId = null,
    ): bool {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.transport = :transport')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.startAt < :endAt')
            ->andWhere('r.endAt > :startAt')
            ->setParameter('transport', $transport)
            ->setParameter('statuses', self::blockingStatuses())
            ->setParameter('startAt', $startAt)
            ->setParameter('endAt', $endAt);

        if (null !== $excludeContractId && '' !== $excludeContractId) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeContractId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countBlockingByAgency(Agency $agency): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.agency = :agency')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('agency', $agency)
            ->setParameter('statuses', self::blockingStatuses())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<AgencyRentalContract>
     */
    public function findBlockingByAgency(Agency $agency, int $limit): array
    {
        /** @var list<AgencyRentalContract> $rows */
        $rows = $this->createQueryBuilder('r')
            ->where('r.agency = :agency')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('agency', $agency)
            ->setParameter('statuses', self::blockingStatuses())
            ->orderBy('r.startAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
