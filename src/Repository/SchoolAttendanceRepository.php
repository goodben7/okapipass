<?php

namespace App\Repository;

use App\Entity\SchoolAttendance;
use App\Entity\SchoolContract;
use App\Entity\SchoolStudent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SchoolAttendance> */
class SchoolAttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolAttendance::class);
    }

    public function findOneForStudentOnDate(SchoolStudent $student, \DateTimeImmutable $date): ?SchoolAttendance
    {
        return $this->findOneBy([
            'student' => $student,
            'attendanceDate' => $date,
        ]);
    }

    /**
     * @return list<SchoolAttendance>
     */
    public function findByContractOnDate(SchoolContract $contract, \DateTimeImmutable $date): array
    {
        /** @var list<SchoolAttendance> $rows */
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.contract = :contract')
            ->andWhere('a.attendanceDate = :date')
            ->setParameter('contract', $contract)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
