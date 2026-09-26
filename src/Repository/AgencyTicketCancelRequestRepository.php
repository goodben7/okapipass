<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyTicketCancelRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyTicketCancelRequest> */
class AgencyTicketCancelRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyTicketCancelRequest::class);
    }

    public function findPendingForTicket(string $ticketId): ?AgencyTicketCancelRequest
    {
        return $this->findOneBy([
            'ticket' => $ticketId,
            'status' => AgencyTicketCancelRequest::STATUS_PENDING,
        ]);
    }

    public function countPendingForAgency(Agency $agency): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.agency = :agency')
            ->andWhere('r.status = :status')
            ->setParameter('agency', $agency)
            ->setParameter('status', AgencyTicketCancelRequest::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
