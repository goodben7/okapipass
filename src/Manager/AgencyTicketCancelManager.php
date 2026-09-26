<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Domain\Agency\AgencyStaffRole;
use App\Dto\Agency\CreateAgencyTicketCancelRequestDto;
use App\Dto\Agency\RejectAgencyTicketCancelRequestDto;
use App\Entity\AgencyTicket;
use App\Entity\AgencyTicketCancelRequest;
use App\Exception\ConflictException;
use App\Exception\UnauthorizedActionException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyTicketCancelRequestRepository;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class AgencyTicketCancelManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyTicketRepository $tickets,
        private AgencyTicketCancelRequestRepository $cancelRequests,
        private AgencyBookingManager $bookingManager,
        private AgencyAuditLogManager $auditLog,
    ) {
    }

    public function createRequest(string $ticketId, CreateAgencyTicketCancelRequestDto $dto): AgencyTicketCancelRequest
    {
        $this->agencyContext->requirePermission(AgencyPermission::TICKET_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $ticket = $this->tickets->find($ticketId);
        if (!$ticket instanceof AgencyTicket || $ticket->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException('Ticket not found.');
        }

        $this->assertTicketCancelable($ticket);

        $existing = $this->cancelRequests->findPendingForTicket((string) $ticket->getId());
        if ($existing instanceof AgencyTicketCancelRequest) {
            throw new ConflictException('A pending cancel request already exists for this ticket.');
        }

        $request = new AgencyTicketCancelRequest();
        $request->setAgency($agency);
        $request->setTicket($ticket);
        $request->setRequestedBy($this->agencyContext->getUser());
        $request->setReason(trim((string) $dto->reason));
        $request->setStatus(AgencyTicketCancelRequest::STATUS_PENDING);

        $this->em->persist($request);
        $this->em->flush();

        return $request;
    }

    public function approve(AgencyTicketCancelRequest $request): AgencyTicketCancelRequest
    {
        $this->assertReviewer();
        $this->agencyContext->assertOwns($request->getAgency());

        if (AgencyTicketCancelRequest::STATUS_PENDING !== $request->getStatus()) {
            throw new ConflictException('Only pending cancel requests can be approved.');
        }

        $ticket = $request->getTicket();
        if (!$ticket instanceof AgencyTicket) {
            throw new UnprocessableEntityException('Cancel request has no ticket.');
        }

        $this->assertTicketCancelable($ticket);

        $this->bookingManager->updateTicketStatus($ticket, AgencyTicket::STATUS_CANCELLED);

        $request->setStatus(AgencyTicketCancelRequest::STATUS_APPROVED);
        $request->setReviewedBy($this->agencyContext->getUser());
        $request->setReviewedAt(new \DateTimeImmutable());

        $this->em->flush();

        $agency = $request->getAgency();
        if (null !== $agency) {
            $this->auditLog->log(
                $agency,
                $this->agencyContext->getUser(),
                'ticket.cancel.approve',
                'AgencyTicketCancelRequest',
                (string) $request->getId(),
                ['ticketId' => $ticket->getId()],
            );
        }

        return $request;
    }

    public function reject(
        AgencyTicketCancelRequest $request,
        RejectAgencyTicketCancelRequestDto $dto,
    ): AgencyTicketCancelRequest {
        $this->assertReviewer();
        $this->agencyContext->assertOwns($request->getAgency());

        if (AgencyTicketCancelRequest::STATUS_PENDING !== $request->getStatus()) {
            throw new ConflictException('Only pending cancel requests can be rejected.');
        }

        $request->setStatus(AgencyTicketCancelRequest::STATUS_REJECTED);
        $request->setReviewedBy($this->agencyContext->getUser());
        $request->setReviewedAt(new \DateTimeImmutable());
        $request->setReviewNotes($dto->notes);

        $this->em->flush();

        return $request;
    }

    public function assertTicketCancelable(AgencyTicket $ticket): void
    {
        if (AgencyTicket::STATUS_ISSUED !== $ticket->getStatus()) {
            throw new UnprocessableEntityException('Only issued tickets can be cancelled via this flow.');
        }

        $now = new \DateTimeImmutable();
        $windowHours = (int) ($ticket->getAgency()?->getCancelWindowHours() ?? AgencyTicketCancelRequest::CANCEL_WINDOW_HOURS);
        $createdAt = $ticket->getCreatedAt();
        $withinWindow = $createdAt instanceof \DateTimeImmutable
            && $createdAt >= $now->modify(sprintf('-%d hours', $windowHours));

        $travelDate = $ticket->getTravelDate();
        $futureTravel = $travelDate instanceof \DateTimeImmutable
            && $travelDate > $now->setTime(0, 0);

        if (!$withinWindow && !$futureTravel) {
            throw new UnprocessableEntityException(sprintf(
                'Ticket is outside the limited cancel window (%d hours after issue or before travel date).',
                $windowHours,
            ));
        }
    }

    private function assertReviewer(): void
    {
        $role = $this->agencyContext->resolveStaffRole();
        $permissions = $this->agencyContext->defaultPermissions();

        if (AgencyStaffRole::SUPERVISOR === $role
            || \in_array(AgencyPermission::REFUND_WRITE, $permissions, true)
            || $this->agencyContext->isElevated()
        ) {
            return;
        }

        throw new UnauthorizedActionException('Supervisor or refund:write permission required.');
    }
}
