<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyStaffMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyStaffMember> */
class AgencyStaffMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyStaffMember::class);
    }

    public function findActiveForUser(User $user): ?AgencyStaffMember
    {
        return $this->findOneBy(['user' => $user, 'active' => true]);
    }

    public function findOneByAgencyAndUser(Agency $agency, User $user): ?AgencyStaffMember
    {
        return $this->findOneBy(['agency' => $agency, 'user' => $user]);
    }
}
