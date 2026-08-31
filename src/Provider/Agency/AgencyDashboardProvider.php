<?php

namespace App\Provider\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AgencyDashboardResource;
use App\Domain\Agency\AgencyFleetOverviewService;
use App\Domain\Agency\SeatOccupancyService;
use App\Entity\AgencyBooking;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTransport;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyTicketRepository;
use App\Repository\AgencyTransportRepository;
use App\Repository\PassDeclarationRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProviderInterface<AgencyDashboardResource> */
final class AgencyDashboardProvider implements ProviderInterface
{
    public function __construct(
        private AgencyContext $agencyContext,
        private AgencyTicketRepository $tickets,
        private AgencyBookingRepository $bookings,
        private AgencyTransportRepository $transports,
        private AgencyOfferRepository $offers,
        private PassDeclarationRepository $declarations,
        private AgencyEmbarkationRepository $embarkations,
        private SeatOccupancyService $occupancy,
        private AgencyFleetOverviewService $fleetOverview,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgencyDashboardResource
    {
        $agency = $this->agencyContext->requireAgency();
        $today = new \DateTimeImmutable('today');

        $ticketsToday = (int) $this->tickets->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.agency = :agency')
            ->andWhere('t.createdAt >= :start')
            ->andWhere('t.createdAt < :end')
            ->setParameter('agency', $agency)
            ->setParameter('start', $today)
            ->setParameter('end', $today->modify('+1 day'))
            ->getQuery()
            ->getSingleScalarResult();

        $activeBookings = (int) $this->bookings->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.agency = :agency')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('agency', $agency)
            ->setParameter('statuses', [AgencyBooking::STATUS_PENDING, AgencyBooking::STATUS_CONFIRMED])
            ->getQuery()
            ->getSingleScalarResult();

        $activeTransports = (int) $this->transports->createQueryBuilder('tr')
            ->select('COUNT(tr.id)')
            ->andWhere('tr.agency = :agency')
            ->andWhere('tr.status = :status')
            ->setParameter('agency', $agency)
            ->setParameter('status', AgencyTransport::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $summary = $this->declarations->summarizeForAgency($agency);

        $recentTickets = [];
        foreach ($this->tickets->findBy(['agency' => $agency], ['createdAt' => 'DESC'], 5) as $ticket) {
            /** @var AgencyTicket $ticket */
            $recentTickets[] = [
                'id' => $ticket->getId(),
                'reference' => $ticket->getReference(),
                'status' => $ticket->getStatus(),
                'passengerName' => $ticket->getPassengerName(),
                'travelDate' => $ticket->getTravelDate()?->format('Y-m-d'),
                'seatNumber' => $ticket->getSeatNumber(),
            ];
        }

        $recentDeclarations = [];
        foreach ($this->declarations->findRecent($agency, 5) as $decl) {
            $recentDeclarations[] = [
                'id' => $decl->getId(),
                'label' => $decl->getLabel(),
                'status' => $decl->getStatus(),
                'fptTotal' => $decl->getFptTotal(),
                'currency' => $decl->getCurrency(),
                'createdAt' => $decl->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        $departuresToday = [];
        $activeOffers = $this->offers->findActiveByAgency($agency);
        foreach ($activeOffers as $offer) {
            $occ = $this->occupancy->availability($offer, $today);
            $emb = $this->embarkations->findOneForOfferOnDate($offer, $today);
            $departuresToday[] = [
                'offerId' => $offer->getId(),
                'label' => $offer->getLabel(),
                'departureTime' => $offer->getDepartureTime(),
                'capacity' => $occ['capacity'],
                'occupied' => $occ['capacity'] - $occ['availableCount'],
                'embarkationId' => $emb?->getId(),
            ];
        }

        return new AgencyDashboardResource(
            id: 'dashboard',
            ticketsToday: $ticketsToday,
            activeBookings: $activeBookings,
            fptDue: $summary['fptDue'],
            activeTransports: $activeTransports,
            recentTickets: $recentTickets,
            recentDeclarations: $recentDeclarations,
            departuresToday: $departuresToday,
            fleet: $this->fleetOverview->buildOverview($agency)['kpis'],
        );
    }
}
