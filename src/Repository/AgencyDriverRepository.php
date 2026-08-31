<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyDriver;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyRentalContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgencyDriver>
 */
class AgencyDriverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyDriver::class);
    }

    public function findOneByAgencyAndLicense(Agency $agency, string $licenseNumber): ?AgencyDriver
    {
        return $this->findOneBy([
            'agency' => $agency,
            'licenseNumber' => strtoupper(trim($licenseNumber)),
        ]);
    }

    public function countEmbarkations(AgencyDriver $driver): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(\App\Entity\AgencyEmbarkation::class, 'e')
            ->where('e.driver = :driver')
            ->setParameter('driver', $driver)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveByAgency(Agency $agency): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.agency = :agency')
            ->andWhere('d.status = :status')
            ->setParameter('agency', $agency)
            ->setParameter('status', AgencyDriver::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countExpiringLicenses(
        Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
    ): int {
        unset($from);

        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.agency = :agency')
            ->andWhere('d.licenseExpiresAt IS NOT NULL')
            ->andWhere('d.licenseExpiresAt <= :until')
            ->setParameter('agency', $agency)
            ->setParameter('until', $until)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOnDutyToday(Agency $agency, \DateTimeImmutable $today): int
    {
        $dayStart = $today->setTime(0, 0);
        $dayEnd = $today->setTime(23, 59, 59);

        $embarkationDrivers = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT IDENTITY(e.driver)')
            ->from(AgencyEmbarkation::class, 'e')
            ->where('e.agency = :agency')
            ->andWhere('e.driver IS NOT NULL')
            ->andWhere('e.departureDate = :today')
            ->setParameter('agency', $agency)
            ->setParameter('today', $dayStart)
            ->getQuery()
            ->getSingleColumnResult();

        $rentalDrivers = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT IDENTITY(r.driver)')
            ->from(AgencyRentalContract::class, 'r')
            ->where('r.agency = :agency')
            ->andWhere('r.driver IS NOT NULL')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.startAt <= :dayEnd')
            ->andWhere('r.endAt >= :dayStart')
            ->setParameter('agency', $agency)
            ->setParameter('statuses', AgencyRentalContractRepository::blockingStatuses())
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->getQuery()
            ->getSingleColumnResult();

        $ids = array_unique(array_filter(array_merge($embarkationDrivers, $rentalDrivers)));

        return \count($ids);
    }

    /**
     * @return list<AgencyDriver>
     */
    public function findExpiringLicenses(
        Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
        int $limit,
    ): array {
        unset($from);

        /** @var list<AgencyDriver> $rows */
        $rows = $this->createQueryBuilder('d')
            ->where('d.agency = :agency')
            ->andWhere('d.licenseExpiresAt IS NOT NULL')
            ->andWhere('d.licenseExpiresAt <= :until')
            ->setParameter('agency', $agency)
            ->setParameter('until', $until)
            ->orderBy('d.licenseExpiresAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<AgencyDriver>
     */
    public function findAllExpiringUntil(\DateTimeImmutable $until): array
    {
        /** @var list<AgencyDriver> $rows */
        $rows = $this->createQueryBuilder('d')
            ->where('d.licenseExpiresAt IS NOT NULL')
            ->andWhere('d.licenseExpiresAt <= :until')
            ->andWhere('d.status = :status')
            ->setParameter('until', $until)
            ->setParameter('status', AgencyDriver::STATUS_ACTIVE)
            ->orderBy('d.licenseExpiresAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
