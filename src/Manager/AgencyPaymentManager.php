<?php

namespace App\Manager;

use App\Domain\Agency\AgencyStaffRole;
use App\Dto\Agency\CreateAgencyPaymentDto;
use App\Entity\AgencyBooking;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Entity\PassDeclaration;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyPaymentRepository;
use App\Repository\AgencyTicketRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

class AgencyPaymentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyPaymentRepository $payments,
        private AgencyTicketRepository $tickets,
    ) {
    }

    public function collect(CreateAgencyPaymentDto $dto): AgencyPayment
    {
        $this->agencyContext->requirePermission(AgencyStaffRole::PAYMENT_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $ticket = $this->resolveTicket((string) $dto->ticket, $agency->getId());

        if ($ticket->isCancelled()) {
            throw new UnprocessableEntityException('Cannot collect payment for a cancelled ticket.');
        }
        if (null !== $this->payments->findPaidForTicket($ticket)) {
            throw new ConflictException('Ticket already has a paid agency payment.');
        }
        if (!$agency->supportsCurrency($ticket->getCurrency())) {
            throw new UnprocessableEntityException(sprintf(
                'Currency %s is not supported by this agency.',
                $ticket->getCurrency()
            ));
        }

        $amount = $ticket->getTicketPrice() + $ticket->getPassPrice();
        $payment = new AgencyPayment();
        $payment->setAgency($agency);
        $payment->setTicket($ticket);
        $payment->setReference($this->nextReference($agency->getId()));
        $payment->setAmount($amount);
        $payment->setCurrency($ticket->getCurrency());
        $payment->setMethod((string) $dto->method);
        $payment->setStatus(AgencyPayment::STATUS_PAID);
        $payment->setChannel(AgencyPayment::CHANNEL_DESK);
        $payment->setPaidAt(new \DateTimeImmutable('now'));
        $payment->setCollectedBy($this->agencyContext->getUser());
        $payment->setNotes($dto->notes);

        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    public function refundPayment(AgencyPayment $payment): AgencyPayment
    {
        $this->agencyContext->requirePermission(AgencyStaffRole::REFUND_WRITE);
        $this->agencyContext->assertOwns($payment->getAgency());

        if (AgencyPayment::STATUS_REFUNDED === $payment->getStatus()) {
            return $payment;
        }
        if (AgencyPayment::STATUS_PAID !== $payment->getStatus()) {
            throw new UnprocessableEntityException('Only PAID payments can be refunded.');
        }

        $payment->setStatus(AgencyPayment::STATUS_REFUNDED);
        $payment->setRefundedAt(new \DateTimeImmutable('now'));

        $ticket = $payment->getTicket();
        if (null !== $ticket) {
            $this->cancelTicketSideEffects($ticket);
        }

        $this->em->flush();

        return $payment;
    }

    public function refundTicket(AgencyTicket $ticket): AgencyTicket
    {
        $this->agencyContext->requirePermission(AgencyStaffRole::REFUND_WRITE);
        $this->agencyContext->assertOwns($ticket->getAgency());

        $payment = $this->payments->findPaidForTicket($ticket);
        if (null !== $payment) {
            $this->refundPayment($payment);

            return $ticket;
        }

        $this->cancelTicketSideEffects($ticket);
        $this->em->flush();

        return $ticket;
    }

    private function cancelTicketSideEffects(AgencyTicket $ticket): void
    {
        if (!$ticket->isCancelled()) {
            $ticket->setStatus(AgencyTicket::STATUS_CANCELLED);
        }

        $booking = $ticket->getBooking();
        if (null !== $booking && !$booking->isCancelled()) {
            $booking->setStatus(AgencyBooking::STATUS_CANCELLED);
        }

        // Free embarkation link
        if (null !== $ticket->getEmbarkation()) {
            $ticket->setEmbarkation(null);
        }

        // If linked to a draft FPT declaration, detach (FPT reverse for draft only)
        $declaration = $ticket->getDeclaration();
        if (null !== $declaration && PassDeclaration::STATUS_DRAFT === $declaration->getStatus()) {
            $ticket->setDeclaration(null);
            foreach ($declaration->getLines() as $line) {
                if ($line->getReferenceBillet() === $ticket->getReference()) {
                    $declaration->getLines()->removeElement($line);
                    $this->em->remove($line);
                }
            }
            $declaration->recalculateFptTotal();
        }
    }

    private function resolveTicket(string $ref, ?string $agencyId): AgencyTicket
    {
        $id = trim($ref);
        if (str_contains($id, '/')) {
            $parts = explode('/', rtrim($id, '/'));
            $id = (string) end($parts);
        }
        $ticket = $this->tickets->find($id);
        if (null === $ticket || $ticket->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException('Ticket not found.');
        }

        return $ticket;
    }

    private function nextReference(?string $agencyId): string
    {
        return sprintf('CAP-%s-%s', substr((string) $agencyId, -4), strtoupper(bin2hex(random_bytes(3))));
    }
}
