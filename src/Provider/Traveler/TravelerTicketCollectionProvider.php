<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TravelerTicketResource;
use App\Entity\AgencyTicket;
use App\Manager\TravelerTicketManager;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<list<TravelerTicketResource>> */
final class TravelerTicketCollectionProvider implements ProviderInterface
{
    public function __construct(
        private TravelerTicketManager $manager,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $scope = $this->requestStack->getCurrentRequest()?->query->get('scope');
        $scope = \is_string($scope) ? $scope : null;
        $tickets = $this->manager->listTickets($scope);

        return array_map(fn (AgencyTicket $t) => $this->map($t), $tickets);
    }

    public function map(AgencyTicket $ticket): TravelerTicketResource
    {
        $offer = $ticket->getOffer();
        $agency = $ticket->getAgency();

        return new TravelerTicketResource(
            id: (string) $ticket->getId(),
            reference: $ticket->getReference(),
            passengerName: $ticket->getPassengerName(),
            passengerPhone: $ticket->getPassengerPhone(),
            seatNumber: $ticket->getSeatNumber(),
            travelDate: $ticket->getTravelDate()?->format('Y-m-d'),
            status: $ticket->getStatus(),
            ticketPrice: $ticket->getTicketPrice(),
            passPrice: $ticket->getPassPrice(),
            discountAmount: $ticket->getDiscountAmount(),
            promoCode: $ticket->getPromoCode(),
            currency: $ticket->getCurrency(),
            agency: null !== $agency ? [
                'id' => $agency->getId(),
                'name' => $agency->getName(),
                'phone' => $agency->getPhone(),
            ] : null,
            offer: null !== $offer ? [
                'id' => $offer->getId(),
                'label' => $offer->getLabel(),
                'origin' => $offer->getOrigin(),
                'destination' => $offer->getDestination(),
                'departureTime' => $offer->getDepartureTime(),
            ] : null,
            pdfUrl: sprintf('/api/traveler/tickets/%s/pdf', $ticket->getId()),
        );
    }
}
