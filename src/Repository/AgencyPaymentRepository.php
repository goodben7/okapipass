<?php

namespace App\Repository;

use App\Entity\AgencyBooking;
use App\Entity\AgencyPayment;
use App\Entity\AgencyRentalContract;
use App\Entity\AgencyTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyPayment> */
class AgencyPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyPayment::class);
    }

    public function findPaidForTicket(AgencyTicket $ticket): ?AgencyPayment
    {
        return $this->findOneBy([
            'ticket' => $ticket,
            'status' => AgencyPayment::STATUS_PAID,
        ]);
    }

    public function findOpenForBooking(AgencyBooking $booking): ?AgencyPayment
    {
        return $this->findOneBy([
            'booking' => $booking,
            'status' => AgencyPayment::STATUS_PENDING,
        ]);
    }

    public function findOneByProviderTransactionId(string $transactionId): ?AgencyPayment
    {
        return $this->findOneBy(['providerTransactionId' => $transactionId]);
    }

    public function findLatestForBooking(AgencyBooking $booking): ?AgencyPayment
    {
        return $this->findOneBy(['booking' => $booking], ['createdAt' => 'DESC']);
    }

    public function findPaidForRentalContract(AgencyRentalContract $contract): ?AgencyPayment
    {
        return $this->findOneBy([
            'rentalContract' => $contract,
            'status' => AgencyPayment::STATUS_PAID,
        ]);
    }

    public function findOpenForRentalContract(AgencyRentalContract $contract): ?AgencyPayment
    {
        return $this->findOneBy([
            'rentalContract' => $contract,
            'status' => AgencyPayment::STATUS_PENDING,
        ]);
    }

    public function findLatestForRentalContract(AgencyRentalContract $contract): ?AgencyPayment
    {
        return $this->findOneBy(['rentalContract' => $contract], ['createdAt' => 'DESC']);
    }
}
