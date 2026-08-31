<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPricingService;
use App\Dto\Agency\AddEmbarkationTicketsDto;
use App\Dto\Agency\CreateAgencyEmbarkationDto;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTransport;
use App\Entity\DeclarationLine;
use App\Entity\PassDeclaration;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyTicketRepository;
use App\Repository\AgencyTransportRepository;
use App\Manager\AgencyDriverManager;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

class AgencyEmbarkationManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyOfferRepository $offers,
        private AgencyTransportRepository $transports,
        private AgencyTicketRepository $tickets,
        private AgencyPricingService $pricing,
        private AgencyDriverManager $drivers,
    ) {
    }

    public function create(CreateAgencyEmbarkationDto $dto): AgencyEmbarkation
    {
        $agency = $this->agencyContext->requireAgency();
        $offer = $this->resolveOffer((string) $dto->offer, $agency->getId());
        $transport = $this->resolveTransport((string) $dto->transport, $agency->getId());

        $embarkation = new AgencyEmbarkation();
        $embarkation->setAgency($agency);
        $embarkation->setLabel((string) $dto->label);
        $embarkation->setOffer($offer);
        $embarkation->setTransport($transport);
        $embarkation->setDepartureDate($this->parseDate((string) $dto->departureDate));
        $embarkation->setDepartureTime((string) $dto->departureTime);
        $embarkation->setNotes($dto->notes);
        $embarkation->setDriver($this->drivers->resolveForAssignment($dto->driver, $agency->getId()));
        $embarkation->setStatus(AgencyEmbarkation::STATUS_PLANNED);

        $this->em->persist($embarkation);
        $this->em->flush();

        if (!empty($dto->ticketIds)) {
            $this->addTickets($embarkation, new AddEmbarkationTicketsDto($dto->ticketIds));
        }

        return $embarkation;
    }

    public function addTickets(AgencyEmbarkation $embarkation, AddEmbarkationTicketsDto $dto): AgencyEmbarkation
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());
        if (\in_array($embarkation->getStatus(), [AgencyEmbarkation::STATUS_DECLARED, AgencyEmbarkation::STATUS_CLOSED], true)) {
            throw new UnprocessableEntityException('Cannot modify tickets on a declared/closed embarkation.');
        }

        foreach ($dto->ticketIds ?? [] as $ticketId) {
            $ticket = $this->resolveTicket((string) $ticketId, $embarkation->getAgency()->getId());
            if ($ticket->isCancelled()) {
                throw new UnprocessableEntityException(sprintf('Ticket %s is cancelled.', $ticket->getId()));
            }
            if (null !== $ticket->getEmbarkation() && $ticket->getEmbarkation()->getId() !== $embarkation->getId()) {
                throw new ConflictException(sprintf('Ticket %s already belongs to another embarkation.', $ticket->getId()));
            }
            $ticket->setEmbarkation($embarkation);
            if (AgencyTicket::STATUS_ISSUED === $ticket->getStatus()) {
                $ticket->setStatus(AgencyTicket::STATUS_BOARDED);
            }
        }

        if (AgencyEmbarkation::STATUS_PLANNED === $embarkation->getStatus()) {
            $embarkation->setStatus(AgencyEmbarkation::STATUS_BOARDING);
        }

        $this->em->flush();

        return $embarkation;
    }

    public function removeTicket(AgencyEmbarkation $embarkation, string $ticketId): void
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());
        if (\in_array($embarkation->getStatus(), [AgencyEmbarkation::STATUS_DECLARED, AgencyEmbarkation::STATUS_CLOSED], true)) {
            throw new UnprocessableEntityException('Cannot modify tickets on a declared/closed embarkation.');
        }

        $ticket = $this->resolveTicket($ticketId, $embarkation->getAgency()->getId());
        if ($ticket->getEmbarkation()?->getId() !== $embarkation->getId()) {
            throw new UnavailableDataException('Ticket not found on this embarkation.');
        }

        $ticket->setEmbarkation(null);
        if (AgencyTicket::STATUS_BOARDED === $ticket->getStatus()) {
            $ticket->setStatus(AgencyTicket::STATUS_ISSUED);
        }
        $this->em->flush();
    }

    public function updateStatus(AgencyEmbarkation $embarkation, string $status): AgencyEmbarkation
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());
        $current = $embarkation->getStatus();

        if (AgencyEmbarkation::STATUS_BOARDING === $status) {
            if (!\in_array($current, [AgencyEmbarkation::STATUS_PLANNED, AgencyEmbarkation::STATUS_BOARDING], true)) {
                throw new UnprocessableEntityException('Invalid status transition to BOARDING.');
            }
            $embarkation->setStatus(AgencyEmbarkation::STATUS_BOARDING);
        } elseif (AgencyEmbarkation::STATUS_DEPARTED === $status) {
            if (!\in_array($current, [AgencyEmbarkation::STATUS_PLANNED, AgencyEmbarkation::STATUS_BOARDING], true)) {
                throw new UnprocessableEntityException('Invalid status transition to DEPARTED.');
            }
            $embarkation->setStatus(AgencyEmbarkation::STATUS_DEPARTED);
            $embarkation->setDepartedAt(new \DateTimeImmutable('now'));
        } elseif (AgencyEmbarkation::STATUS_CLOSED === $status) {
            if (AgencyEmbarkation::STATUS_DECLARED !== $current) {
                throw new UnprocessableEntityException('Only DECLARED embarkations can be CLOSED.');
            }
            $embarkation->setStatus(AgencyEmbarkation::STATUS_CLOSED);
        } else {
            throw new UnprocessableEntityException(sprintf('Unsupported status %s.', $status));
        }

        $this->em->flush();

        return $embarkation;
    }

    public function declare(AgencyEmbarkation $embarkation): PassDeclaration
    {
        $this->agencyContext->assertOwns($embarkation->getAgency());

        if (null !== $embarkation->getDeclaration()) {
            // Idempotent: return existing (AC-04 friendly)
            return $embarkation->getDeclaration();
        }

        if (AgencyEmbarkation::STATUS_CLOSED === $embarkation->getStatus()) {
            throw new UnprocessableEntityException('Cannot declare a closed embarkation.');
        }

        $declaration = new PassDeclaration();
        $declaration->setAgency($embarkation->getAgency());
        $declaration->setLabel(sprintf('FPT %s', $embarkation->getLabel()));
        $declaration->setSource(PassDeclaration::SOURCE_EMBARKATION);
        $declaration->setStatus(PassDeclaration::STATUS_SUBMITTED);
        $declaration->setSubmittedAt(new \DateTimeImmutable('now'));
        $declaration->setCurrency($embarkation->getAgency()->getDefaultCurrency());

        foreach ($embarkation->getTickets() as $ticket) {
            if ($ticket->isCancelled()) {
                continue;
            }
            $line = $this->lineFromTicket($ticket);
            $declaration->addLine($line);
            $ticket->setDeclaration($declaration);
            if (AgencyTicket::STATUS_ISSUED === $ticket->getStatus()) {
                $ticket->setStatus(AgencyTicket::STATUS_BOARDED);
            }
        }

        $declaration->recalculateFptTotal();
        $embarkation->setDeclaration($declaration);
        $embarkation->setStatus(AgencyEmbarkation::STATUS_DECLARED);
        $embarkation->setDeclaredAt(new \DateTimeImmutable('now'));
        $declaration->setEmbarkation($embarkation);

        $this->em->persist($declaration);
        $this->em->flush();

        return $declaration;
    }

    private function lineFromTicket(AgencyTicket $ticket): DeclarationLine
    {
        $offer = $ticket->getOffer();
        $quote = $this->pricing->quote($ticket->getOkapiPassRef());

        $line = new DeclarationLine();
        $line->setReferenceBillet((string) $ticket->getReference());
        $line->setDate($ticket->getTravelDate());
        $line->setPassengerName((string) $ticket->getPassengerName());
        $line->setPassengerId((string) $ticket->getPassengerId());
        $line->setOrigin((string) ($offer?->getOrigin() ?? ''));
        $line->setDestination((string) ($offer?->getDestination() ?? ''));
        $line->setTicketPrice($ticket->getTicketPrice());
        $line->setCurrency($ticket->getCurrency());
        $line->setPassPrice($ticket->getPassPrice() > 0 ? $ticket->getPassPrice() : $quote['passPrice']);
        $line->setOkapiPassRef($ticket->getOkapiPassRef());
        $line->setHasExistingPass($ticket->hasExistingPass());

        return $line;
    }

    private function resolveOffer(string $ref, ?string $agencyId): AgencyOffer
    {
        $id = $this->extractId($ref);
        $offer = $this->offers->find($id);
        if (null === $offer || $offer->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException('Offer not found.');
        }

        return $offer;
    }

    private function resolveTransport(string $ref, ?string $agencyId): AgencyTransport
    {
        $id = $this->extractId($ref);
        $transport = $this->transports->find($id);
        if (null === $transport || $transport->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException('Transport not found.');
        }

        return $transport;
    }

    private function resolveTicket(string $ref, ?string $agencyId): AgencyTicket
    {
        $id = $this->extractId($ref);
        $ticket = $this->tickets->find($id);
        if (null === $ticket || $ticket->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('Ticket "%s" not found.', $id));
        }

        return $ticket;
    }

    private function extractId(string $ref): string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));

            return (string) end($parts);
        }

        return $ref;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (false === $d) {
            throw new UnprocessableEntityException('Invalid departureDate.');
        }

        return $d->setTime(0, 0);
    }
}
