<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyDriver;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyEmbarkation> */
class AgencyEmbarkationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyEmbarkation::class);
    }

    public function findOneForOfferOnDate(AgencyOffer $offer, \DateTimeImmutable $date): ?AgencyEmbarkation
    {
        return $this->findOneBy([
            'offer' => $offer,
            'departureDate' => $date,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * @return list<AgencyEmbarkation>
     */
    public function findRecentByDriver(AgencyDriver $driver, int $limit): array
    {
        /** @var list<AgencyEmbarkation> $rows */
        $rows = $this->createQueryBuilder('e')
            ->where('e.driver = :driver')
            ->setParameter('driver', $driver)
            ->orderBy('e.departureDate', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<AgencyEmbarkation>
     */
    public function findByTransportOnDate(AgencyTransport $transport, \DateTimeImmutable $date): array
    {
        /** @var list<AgencyEmbarkation> $rows */
        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.transport = :transport')
            ->andWhere('e.departureDate = :date')
            ->andWhere('e.status NOT IN (:closed)')
            ->setParameter('transport', $transport)
            ->setParameter('date', $date)
            ->setParameter('closed', [
                AgencyEmbarkation::STATUS_CLOSED,
                AgencyEmbarkation::STATUS_DECLARED,
            ])
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<AgencyEmbarkation>
     */
    public function findByDriverOnDate(AgencyDriver $driver, \DateTimeImmutable $date): array
    {
        /** @var list<AgencyEmbarkation> $rows */
        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.driver = :driver')
            ->andWhere('e.departureDate = :date')
            ->andWhere('e.status NOT IN (:closed)')
            ->setParameter('driver', $driver)
            ->setParameter('date', $date)
            ->setParameter('closed', [
                AgencyEmbarkation::STATUS_CLOSED,
                AgencyEmbarkation::STATUS_DECLARED,
            ])
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Fleet planning list (Vague 9).
     *
     * @return list<AgencyEmbarkation>
     */
    public function findFleetTrips(
        Agency $agency,
        ?\DateTimeImmutable $date = null,
        ?string $serviceType = null,
        bool $unassignedOnly = false,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.offer', 'o')->addSelect('o')
            ->leftJoin('e.transport', 't')->addSelect('t')
            ->leftJoin('e.driver', 'd')->addSelect('d')
            ->andWhere('e.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('e.departureDate', 'ASC')
            ->addOrderBy('e.departureTime', 'ASC');

        if (null !== $date) {
            $qb->andWhere('e.departureDate = :date')->setParameter('date', $date);
        }
        if (null !== $serviceType && '' !== $serviceType) {
            $qb->andWhere('o.serviceType = :serviceType')->setParameter('serviceType', $serviceType);
        }
        if ($unassignedOnly) {
            $qb->andWhere('e.transport IS NULL');
        }

        /** @var list<AgencyEmbarkation> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }
}
