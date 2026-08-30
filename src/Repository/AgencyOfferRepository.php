<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgencyOffer>
 */
class AgencyOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyOffer::class);
    }

    public function countActiveByTransport(AgencyTransport $transport): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.transport = :transport')
            ->andWhere('o.active = :active')
            ->setParameter('transport', $transport)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByTransport(AgencyTransport $transport): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.transport = :transport')
            ->setParameter('transport', $transport)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<AgencyOffer>
     */
    public function findActiveByAgency(Agency $agency): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.agency = :agency')
            ->andWhere('o.active = :active')
            ->setParameter('agency', $agency)
            ->setParameter('active', true)
            ->orderBy('o.departureTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Base query for public online catalogue (Sprint 2+).
     */
    public function createPublicOnlineQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.agency', 'a')
            ->innerJoin('o.transport', 't')
            ->andWhere('o.active = :active')
            ->andWhere('o.onlineSales = :onlineSales')
            ->andWhere('a.status = :agencyStatus')
            ->andWhere('t.status = :transportStatus')
            ->setParameter('active', true)
            ->setParameter('onlineSales', true)
            ->setParameter('agencyStatus', Agency::STATUS_ACTIVE)
            ->setParameter('transportStatus', AgencyTransport::STATUS_ACTIVE)
            ->orderBy('o.departureTime', 'ASC');
    }

    public function findPublicOnlineById(string $id): ?AgencyOffer
    {
        return $this->createPublicOnlineQueryBuilder()
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<AgencyOffer>
     */
    public function findPublicOnlinePage(
        ?string $origin,
        ?string $destination,
        ?string $agencyId,
        int $offset,
        int $limit,
    ): array {
        $qb = $this->applyPublicOnlineSearchFilters(
            $this->createPublicOnlineQueryBuilder(),
            $origin,
            $destination,
            $agencyId,
        );

        return $qb
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    public function countPublicOnline(?string $origin, ?string $destination, ?string $agencyId): int
    {
        $qb = $this->applyPublicOnlineSearchFilters(
            $this->createQueryBuilder('o')
                ->select('COUNT(o.id)')
                ->innerJoin('o.agency', 'a')
                ->innerJoin('o.transport', 't')
                ->andWhere('o.active = :active')
                ->andWhere('o.onlineSales = :onlineSales')
                ->andWhere('a.status = :agencyStatus')
                ->andWhere('t.status = :transportStatus')
                ->setParameter('active', true)
                ->setParameter('onlineSales', true)
                ->setParameter('agencyStatus', Agency::STATUS_ACTIVE)
                ->setParameter('transportStatus', AgencyTransport::STATUS_ACTIVE),
            $origin,
            $destination,
            $agencyId,
        );

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyPublicOnlineSearchFilters(
        QueryBuilder $qb,
        ?string $origin,
        ?string $destination,
        ?string $agencyId,
    ): QueryBuilder {
        if (null !== $origin && '' !== trim($origin)) {
            $qb->andWhere('LOWER(o.origin) LIKE :origin')
                ->setParameter('origin', '%'.mb_strtolower(trim($origin)).'%');
        }
        if (null !== $destination && '' !== trim($destination)) {
            $qb->andWhere('LOWER(o.destination) LIKE :destination')
                ->setParameter('destination', '%'.mb_strtolower(trim($destination)).'%');
        }
        if (null !== $agencyId && '' !== trim($agencyId)) {
            $qb->andWhere('IDENTITY(o.agency) = :agencyId')
                ->setParameter('agencyId', trim($agencyId));
        }

        return $qb;
    }
}
