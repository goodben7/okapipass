<?php

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AgencyTicket> */
class AgencyTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencyTicket::class);
    }

    /**
     * Manual tickets (no booking) that still occupy a seat.
     *
     * @return list<string>
     */
    public function findActiveManualSeats(AgencyOffer $offer, \DateTimeImmutable $travelDate): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.seatNumber')
            ->andWhere('t.offer = :offer')
            ->andWhere('t.travelDate = :travelDate')
            ->andWhere('t.status NOT IN (:freeStatuses)')
            ->andWhere('t.booking IS NULL')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $travelDate)
            ->setParameter('freeStatuses', [
                AgencyTicket::STATUS_CANCELLED,
                AgencyTicket::STATUS_NO_SHOW,
            ]);

        /** @var list<string> $seats */
        $seats = array_column($qb->getQuery()->getScalarResult(), 'seatNumber');

        return $seats;
    }

    public function countActiveManualForOfferDate(AgencyOffer $offer, \DateTimeImmutable $travelDate): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.offer = :offer')
            ->andWhere('t.travelDate = :travelDate')
            ->andWhere('t.status NOT IN (:freeStatuses)')
            ->andWhere('t.booking IS NULL')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $travelDate)
            ->setParameter('freeStatuses', [
                AgencyTicket::STATUS_CANCELLED,
                AgencyTicket::STATUS_NO_SHOW,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBoardedForOfferDate(AgencyOffer $offer, \DateTimeImmutable $travelDate): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.offer = :offer')
            ->andWhere('t.travelDate = :travelDate')
            ->andWhere('t.status IN (:boarded)')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $travelDate)
            ->setParameter('boarded', [
                AgencyTicket::STATUS_BOARDED,
                AgencyTicket::STATUS_USED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByReference(string $reference): ?AgencyTicket
    {
        return $this->findOneBy(['reference' => strtoupper(trim($reference))]);
    }

    public function countFutureByOffer(AgencyOffer $offer, \DateTimeImmutable $from): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.offer = :offer')
            ->andWhere('t.travelDate >= :from')
            ->andWhere('t.status != :cancelled')
            ->setParameter('offer', $offer)
            ->setParameter('from', $from)
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Tickets not yet attached to a FPT declaration, for a travel-date window.
     *
     * @return list<AgencyTicket>
     */
    public function findUndeclaredForAgencyPeriod(
        Agency $agency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        /** @var list<AgencyTicket> $tickets */
        $tickets = $this->createQueryBuilder('t')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.travelDate >= :from')
            ->andWhere('t.travelDate <= :to')
            ->andWhere('t.status != :cancelled')
            ->andWhere('t.declaration IS NULL')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->orderBy('t.travelDate', 'ASC')
            ->addOrderBy('t.reference', 'ASC')
            ->getQuery()
            ->getResult();

        return $tickets;
    }

    public function countByPassengerPhone(Agency $agency, string $phone): int
    {
        $phone = trim($phone);
        if ('' === $phone) {
            return 0;
        }

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.passengerPhone = :phone')
            ->andWhere('t.status != :cancelled')
            ->setParameter('agency', $agency)
            ->setParameter('phone', $phone)
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByPassengerPhoneAndPromoCode(Agency $agency, string $phone, string $promoCode): int
    {
        $phone = trim($phone);
        $promoCode = strtoupper(trim($promoCode));
        if ('' === $phone || '' === $promoCode) {
            return 0;
        }

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.passengerPhone = :phone')
            ->andWhere('t.promoCode = :promoCode')
            ->andWhere('t.status != :cancelled')
            ->setParameter('agency', $agency)
            ->setParameter('phone', $phone)
            ->setParameter('promoCode', $promoCode)
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<AgencyTicket>
     */
    public function findBenefits(
        Agency $agency,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?string $phone = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.loyaltyRule', 'lr')->addSelect('lr')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.discountAmount > 0')
            ->setParameter('agency', $agency)
            ->orderBy('t.createdAt', 'DESC');

        if (null !== $from) {
            $qb->andWhere('t.createdAt >= :from')->setParameter('from', $from->setTime(0, 0));
        }
        if (null !== $to) {
            $qb->andWhere('t.createdAt <= :to')->setParameter('to', $to->setTime(23, 59, 59));
        }
        $phone = null !== $phone ? trim($phone) : '';
        if ('' !== $phone) {
            $qb->andWhere('t.passengerPhone = :phone')->setParameter('phone', $phone);
        }

        /** @var list<AgencyTicket> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * @return list<AgencyTicket>
     */
    public function findForManifest(AgencyOffer $offer, \DateTimeImmutable $travelDate): array
    {
        /** @var list<AgencyTicket> $rows */
        $rows = $this->createQueryBuilder('t')
            ->andWhere('t.offer = :offer')
            ->andWhere('t.travelDate = :travelDate')
            ->andWhere('t.status NOT IN (:excluded)')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $travelDate)
            ->setParameter('excluded', [AgencyTicket::STATUS_CANCELLED])
            ->orderBy('t.seatNumber', 'ASC')
            ->addOrderBy('t.reference', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<AgencyTicket>
     */
    public function findIssuedForOfferDate(AgencyOffer $offer, \DateTimeImmutable $travelDate): array
    {
        /** @var list<AgencyTicket> $rows */
        $rows = $this->createQueryBuilder('t')
            ->andWhere('t.offer = :offer')
            ->andWhere('t.travelDate = :travelDate')
            ->andWhere('t.status = :issued')
            ->setParameter('offer', $offer)
            ->setParameter('travelDate', $travelDate)
            ->setParameter('issued', AgencyTicket::STATUS_ISSUED)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findForTravelerPhone(string $normalizedPhone, ?string $scope = null): array
    {
        $suffix = substr(preg_replace('/\D/', '', $normalizedPhone) ?? '', -9);
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.offer', 'o')->addSelect('o')
            ->leftJoin('t.agency', 'a')->addSelect('a')
            ->andWhere('t.passengerPhone = :phone OR t.passengerPhone LIKE :suffix')
            ->setParameter('phone', $normalizedPhone)
            ->setParameter('suffix', '%'.$suffix)
            ->orderBy('t.travelDate', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        $today = new \DateTimeImmutable('today');
        if ('upcoming' === $scope) {
            $qb->andWhere('t.travelDate >= :today')
                ->andWhere('t.status != :cancelled')
                ->setParameter('today', $today)
                ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED);
        } elseif ('past' === $scope) {
            $qb->andWhere('t.travelDate < :today')
                ->setParameter('today', $today);
        } elseif ('cancelled' === $scope) {
            $qb->andWhere('t.status = :cancelled')
                ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED);
        }

        /** @var list<AgencyTicket> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * @return list<AgencyTicket>
     */
    public function findIssuedForTravelDate(\DateTimeImmutable $travelDate): array
    {
        /** @var list<AgencyTicket> $rows */
        $rows = $this->createQueryBuilder('t')
            ->leftJoin('t.offer', 'o')->addSelect('o')
            ->andWhere('t.travelDate = :travelDate')
            ->andWhere('t.status = :issued')
            ->setParameter('travelDate', $travelDate->setTime(0, 0))
            ->setParameter('issued', AgencyTicket::STATUS_ISSUED)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findOneByQrToken(string $token): ?AgencyTicket
    {
        return $this->findOneBy(['qrToken' => trim($token)]);
    }

    /**
     * @return list<array{origin: string, destination: string, ticketsSold: int, capacityEstimate: int}>
     */
    public function corridorOccupancy(Agency $agency, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<AgencyTicket> $tickets */
        $tickets = $this->createQueryBuilder('t')
            ->leftJoin('t.offer', 'o')->addSelect('o')
            ->leftJoin('o.transport', 'tr')->addSelect('tr')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.travelDate >= :from')
            ->andWhere('t.travelDate <= :to')
            ->andWhere('t.status != :cancelled')
            ->setParameter('agency', $agency)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getResult();

        /** @var array<string, array{origin: string, destination: string, ticketsSold: int, capacities: array<string, int>}> $grouped */
        $grouped = [];
        foreach ($tickets as $ticket) {
            $offer = $ticket->getOffer();
            $origin = (string) ($offer?->getOrigin() ?? '');
            $destination = (string) ($offer?->getDestination() ?? '');
            $key = $origin.'|'.$destination;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'origin' => $origin,
                    'destination' => $destination,
                    'ticketsSold' => 0,
                    'capacities' => [],
                ];
            }
            ++$grouped[$key]['ticketsSold'];
            $cap = (int) ($offer?->getTransport()?->getCapacity() ?? 0);
            if ($cap > 0) {
                $depKey = ($offer?->getId() ?? '').'|'.($ticket->getTravelDate()?->format('Y-m-d') ?? '');
                $grouped[$key]['capacities'][$depKey] = $cap;
            }
        }

        $out = [];
        foreach ($grouped as $row) {
            $capacityEstimate = array_sum($row['capacities']);
            $out[] = [
                'origin' => $row['origin'],
                'destination' => $row['destination'],
                'ticketsSold' => $row['ticketsSold'],
                'capacityEstimate' => max(1, $capacityEstimate > 0 ? $capacityEstimate : $row['ticketsSold']),
            ];
        }

        return $out;
    }

    /**
     * @return array{ticketsSold: int, capacity: int}
     */
    public function offerDayOccupancy(Agency $agency, string $offerId, \DateTimeImmutable $date): array
    {
        $row = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) AS ticketsSold, MAX(tr.capacity) AS capacity')
            ->join('t.offer', 'o')
            ->leftJoin('o.transport', 'tr')
            ->andWhere('t.agency = :agency')
            ->andWhere('IDENTITY(t.offer) = :offerId')
            ->andWhere('t.travelDate = :date')
            ->andWhere('t.status != :cancelled')
            ->setParameter('agency', $agency)
            ->setParameter('offerId', $offerId)
            ->setParameter('date', $date->setTime(0, 0))
            ->setParameter('cancelled', AgencyTicket::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleResult();

        return [
            'ticketsSold' => (int) ($row['ticketsSold'] ?? 0),
            'capacity' => max(1, (int) ($row['capacity'] ?? 1)),
        ];
    }

    /**
     * Average occupancy % for the same weekday over the last 4–8 weeks (excluding target date).
     */
    public function sameWeekdayOccupancyAvg(
        Agency $agency,
        string $offerId,
        \DateTimeImmutable $date,
        int $maxWeeks = 8,
    ): ?float {
        $weekday = (int) $date->format('w');
        $samples = [];
        for ($w = 1; $w <= $maxWeeks; ++$w) {
            $past = $date->modify(sprintf('-%d weeks', $w))->setTime(0, 0);
            if ((int) $past->format('w') !== $weekday) {
                continue;
            }
            $stats = $this->offerDayOccupancy($agency, $offerId, $past);
            $sold = (int) $stats['ticketsSold'];
            if ($sold <= 0 && $w > 4) {
                continue;
            }
            $capacity = max(1, (int) $stats['capacity']);
            $samples[] = 100 * $sold / $capacity;
        }

        if (\count($samples) < 1) {
            return null;
        }

        return array_sum($samples) / \count($samples);
    }
}
