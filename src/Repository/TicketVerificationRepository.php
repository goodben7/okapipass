<?php

namespace App\Repository;

use App\Entity\TicketVerification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketVerification>
 *
 * @method TicketVerification|null find($id, $lockMode = null, $lockVersion = null)
 * @method TicketVerification|null findOneBy(array $criteria, array $orderBy = null)
 * @method TicketVerification[]    findAll()
 * @method TicketVerification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TicketVerificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketVerification::class);
    }
}
