<?php

namespace App\Manager;

use App\Domain\Agency\AgencyDiscountService;
use App\Domain\Agency\AgencyMinorAccompanimentValidator;
use App\Domain\Agency\AgencyOfferEffectivePriceResolver;
use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateAgencyBookingDto;
use App\Dto\Agency\CreateAgencyPaymentDto;
use App\Dto\Agency\CreateCashHandoverDto;
use App\Dto\Agency\CreatePosSaleDto;
use App\Dto\Agency\OpenPosSessionDto;
use App\Entity\AgencyBooking;
use App\Entity\AgencyPayment;
use App\Entity\AgencyStaffMember;
use App\Entity\CashHandover;
use App\Entity\PosSession;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyOfferRepository;
use App\Repository\AgencyStaffMemberRepository;
use App\Repository\CashHandoverRepository;
use App\Repository\PosSessionRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class PosManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private PosSessionRepository $sessions,
        private CashHandoverRepository $handovers,
        private AgencyBookingManager $bookings,
        private AgencyPaymentManager $payments,
        private AgencyDiscountService $discounts,
        private AgencyOfferRepository $offers,
        private SellerCommissionManager $sellerCommission,
        private AccountingAgencyManager $accounting,
        private AgencyBlacklistManager $blacklist,
        private AgencyMinorAccompanimentValidator $minorValidator,
        private AgencyOfferEffectivePriceResolver $effectivePrice,
        private AgencyAuditLogManager $auditLog,
        private AgencyStaffMemberRepository $staffMembers,
    ) {
    }

    public function openSession(OpenPosSessionDto $dto): PosSession
    {
        $this->agencyContext->requirePermission(AgencyPermission::POS_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $seller = $this->agencyContext->getUser();

        $existing = $this->sessions->findOpenForSeller($agency, $seller);
        if ($existing instanceof PosSession) {
            $incomingDevice = null !== $dto->deviceId && '' !== trim($dto->deviceId) ? trim($dto->deviceId) : null;
            if (null !== $existing->getDeviceId() && null !== $incomingDevice && $existing->getDeviceId() !== $incomingDevice) {
                throw new ConflictException('Seller already has an open POS session on another device.');
            }
            throw new ConflictException('Seller already has an open POS session.');
        }

        $staff = $this->staffMembers->findOneByAgencyAndUser($agency, $seller);
        if ($staff instanceof AgencyStaffMember && null !== $staff->getPinHash() && '' !== $staff->getPinHash()) {
            $pin = (string) ($dto->pin ?? '');
            if ('' === $pin || !password_verify($pin, $staff->getPinHash())) {
                throw new UnprocessableEntityException('Invalid PIN for POS session.');
            }
        }

        $session = new PosSession();
        $session->setAgency($agency);
        $session->setSeller($seller);
        $session->setPointOfSale($dto->pointOfSale);
        $session->setStatus(PosSession::STATUS_OPEN);
        $session->setOpenedAt(new \DateTimeImmutable('now'));
        $session->setExpectedCash(0);
        $session->setNotes($dto->notes);
        $session->setDeviceId($dto->deviceId);

        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }

    public function closeSession(PosSession $session, ?string $deviceId = null): PosSession
    {
        $this->agencyContext->requirePermission(AgencyPermission::POS_WRITE);
        $this->agencyContext->assertOwns($session->getAgency());

        if (PosSession::STATUS_CLOSED === $session->getStatus()) {
            return $session;
        }

        if (null !== $session->getDeviceId() && '' !== $session->getDeviceId()) {
            $incoming = null !== $deviceId ? trim($deviceId) : '';
            if ($incoming !== $session->getDeviceId()) {
                throw new UnprocessableEntityException('deviceId required and must match the session device to close.');
            }
        }

        $session->setStatus(PosSession::STATUS_CLOSED);
        $session->setClosedAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        $this->sellerCommission->accrueForSession($session);

        return $session;
    }

    /**
     * Fast sale: booking → issue ticket → cash/MM payment, linked to open session.
     *
     * @return array{session: PosSession, bookingId: string, ticketId: string, paymentId: string, amount: int}
     */
    public function sale(CreatePosSaleDto $dto): array
    {
        $this->agencyContext->requirePermission(AgencyPermission::POS_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $session = $this->requireOpenSession($dto->session);

        $offerId = $this->extractId((string) $dto->offer, 'offer');
        $offer = $this->offers->find($offerId);
        if (null === $offer || $offer->getAgency()?->getId() !== $agency->getId()) {
            throw new UnavailableDataException(sprintf('Offer "%s" not found.', $offerId));
        }

        $this->blacklist->assertNotBlacklisted($agency, $dto->passengerPhone, $dto->passengerId);

        $travelDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $dto->travelDate);
        if (false === $travelDate) {
            throw new UnprocessableEntityException('Invalid travelDate.');
        }
        $travelDate = $travelDate->setTime(0, 0);

        $passengerDob = null;
        if (null !== $dto->passengerDateOfBirth && '' !== trim($dto->passengerDateOfBirth)) {
            $passengerDob = \DateTimeImmutable::createFromFormat('Y-m-d', $dto->passengerDateOfBirth);
            if (false === $passengerDob) {
                throw new UnprocessableEntityException('Invalid passengerDateOfBirth.');
            }
            $passengerDob = $passengerDob->setTime(0, 0);
        }

        $this->minorValidator->assertAllowed(
            $offer,
            $passengerDob,
            $dto->escortTicketId,
            $dto->escortName,
            $travelDate,
        );

        $baseTicketPrice = $this->effectivePrice->resolve($offer, $travelDate);

        $discountAmount = $dto->discountAmount;
        $promoCode = $dto->promoCode;
        $loyaltyRuleId = null;
        $hasPromo = null !== $promoCode && '' !== trim($promoCode);
        if ($hasPromo || null === $discountAmount) {
            $resolved = $this->discounts->resolveDiscount(
                $agency,
                $baseTicketPrice,
                $hasPromo ? $promoCode : null,
                $dto->passengerPhone,
                $offer,
                $dto->seatNumber,
            );
            $discountAmount = $resolved['discountAmount'];
            if (null !== $resolved['promoCode']) {
                $promoCode = $resolved['promoCode'];
            }
            $loyaltyRuleId = $resolved['loyaltyRuleId'];
        }

        $bookingResult = $this->bookings->create(new CreateAgencyBookingDto(
            offer: $dto->offer,
            passengerName: $dto->passengerName,
            passengerId: $dto->passengerId,
            passengerPhone: $dto->passengerPhone,
            seatNumber: $dto->seatNumber,
            travelDate: $dto->travelDate,
            okapiPassRef: $dto->okapiPassRef,
            status: AgencyBooking::STATUS_CONFIRMED,
            sendSms: $dto->sendSms ?? false,
        ));
        $booking = $bookingResult->booking;
        $booking->setChannel(AgencyBooking::CHANNEL_POS);

        $ticket = $this->bookings->issueTicket($booking);
        if (null !== $passengerDob) {
            $ticket->setPassengerDateOfBirth($passengerDob);
        }
        if (null !== $dto->escortTicketId) {
            $ticket->setEscortTicketId($dto->escortTicketId);
        }
        if (null !== $dto->escortName) {
            $ticket->setEscortName($dto->escortName);
        }
        if ($dto->insuranceOpted) {
            $ticket->setInsuranceOpted(true);
            $ticket->setInsuranceFee(500);
        }
        $ticket->setTicketPrice($baseTicketPrice);
        if (null !== $promoCode && '' !== trim($promoCode)) {
            $ticket->setPromoCode($promoCode);
            $this->discounts->applyPromo($agency, $promoCode);
        }
        if (($discountAmount ?? 0) > 0) {
            $ticket->setDiscountAmount((int) $discountAmount);
            $ticket->setTicketPrice(max(0, $ticket->getTicketPrice() - (int) $discountAmount));
        }
        if ($ticket->isInsuranceOpted()) {
            $ticket->setTicketPrice($ticket->getTicketPrice() + $ticket->getInsuranceFee());
        }
        if (null !== $loyaltyRuleId && '' !== $loyaltyRuleId) {
            $rule = $this->em->getRepository(\App\Entity\LoyaltyRule::class)->find($loyaltyRuleId);
            if ($rule instanceof \App\Entity\LoyaltyRule) {
                $ticket->setLoyaltyRule($rule);
            }
        }
        $this->em->flush();

        $payment = $this->payments->collect(new CreateAgencyPaymentDto(
            ticket: '/api/agency/tickets/'.$ticket->getId(),
            method: $dto->method ?? AgencyPayment::METHOD_CASH,
            notes: sprintf('POS session %s', $session->getId()),
        ));
        $payment->setChannel(AgencyPayment::CHANNEL_POS);

        if (AgencyPayment::METHOD_CASH === $payment->getMethod()) {
            $session->setExpectedCash($session->getExpectedCash() + $payment->getAmount());
        }
        $this->em->flush();

        $this->auditLog->log(
            $agency,
            $this->agencyContext->getUser(),
            'pos.sale',
            'AgencyTicket',
            (string) $ticket->getId(),
            [
                'paymentId' => $payment->getId(),
                'sessionId' => $session->getId(),
                'amount' => $payment->getAmount(),
            ],
        );

        return [
            'session' => $session,
            'bookingId' => (string) $booking->getId(),
            'ticketId' => (string) $ticket->getId(),
            'paymentId' => (string) $payment->getId(),
            'amount' => $payment->getAmount(),
        ];
    }

    public function createHandover(CreateCashHandoverDto $dto): CashHandover
    {
        $this->agencyContext->requirePermission(AgencyPermission::POS_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $session = $this->requireSession($dto->session);
        if (PosSession::STATUS_OPEN === $session->getStatus()) {
            $this->closeSession($session, $session->getDeviceId());
        }

        $handover = new CashHandover();
        $handover->setAgency($agency);
        $handover->setSession($session);
        $handover->setSeller($session->getSeller());
        $handover->setDeclaredAmount((int) $dto->declaredAmount);
        $handover->setExpectedCash($session->getExpectedCash());
        $handover->setCurrency($dto->currency ?? $agency->getDefaultCurrency());
        $handover->setStatus(CashHandover::STATUS_PENDING);
        $handover->setNotes($dto->notes);

        $this->em->persist($handover);
        $this->em->flush();

        return $handover;
    }

    public function confirmHandover(CashHandover $handover): CashHandover
    {
        $this->agencyContext->requirePermission(AgencyPermission::CASH_COLLECT);
        $this->agencyContext->assertOwns($handover->getAgency());

        if (CashHandover::STATUS_CONFIRMED === $handover->getStatus()) {
            return $handover;
        }
        if (CashHandover::STATUS_PENDING !== $handover->getStatus()) {
            throw new UnprocessableEntityException('Only PENDING handovers can be confirmed.');
        }

        $expected = $handover->getSession()?->getExpectedCash() ?? $handover->getExpectedCash();
        $variance = $handover->getDeclaredAmount() - $expected;
        $handover->setExpectedCash($expected);
        $handover->setVariance($variance);
        $handover->setStatus(CashHandover::STATUS_CONFIRMED);
        $handover->setConfirmedBy($this->agencyContext->getUser());
        $handover->setConfirmedAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        $this->accounting->recordCashHandoverVariance($handover);

        $agency = $handover->getAgency();
        if (null !== $agency) {
            $this->auditLog->log(
                $agency,
                $this->agencyContext->getUser(),
                'cash.handover.confirm',
                'CashHandover',
                (string) $handover->getId(),
                ['variance' => $variance, 'declared' => $handover->getDeclaredAmount()],
            );
        }

        return $handover;
    }

    public function rejectHandover(CashHandover $handover, ?string $reason = null): CashHandover
    {
        $this->agencyContext->requirePermission(AgencyPermission::CASH_COLLECT);
        $this->agencyContext->assertOwns($handover->getAgency());

        if (CashHandover::STATUS_PENDING !== $handover->getStatus()) {
            throw new UnprocessableEntityException('Only PENDING handovers can be rejected.');
        }

        $handover->setStatus(CashHandover::STATUS_REJECTED);
        $handover->setRejectionReason($reason);
        $handover->setConfirmedBy($this->agencyContext->getUser());
        $handover->setConfirmedAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        return $handover;
    }

    public function resolveHandover(CashHandover $handover, string $justification): CashHandover
    {
        $this->agencyContext->requirePermission(AgencyPermission::CASH_COLLECT);
        $this->agencyContext->assertOwns($handover->getAgency());

        if (CashHandover::STATUS_RESOLVED === $handover->getStatus()) {
            return $handover;
        }
        if (CashHandover::STATUS_CONFIRMED !== $handover->getStatus()) {
            throw new UnprocessableEntityException('Only CONFIRMED handovers can be resolved.');
        }
        if (0 === $handover->getVariance()) {
            throw new UnprocessableEntityException('Only handovers with variance ≠ 0 can be resolved.');
        }
        $justification = trim($justification);
        if ('' === $justification) {
            throw new UnprocessableEntityException('resolutionJustification is required.');
        }

        $handover->setStatus(CashHandover::STATUS_RESOLVED);
        $handover->setResolutionJustification($justification);
        $handover->setResolvedAt(new \DateTimeImmutable('now'));
        $handover->setResolvedBy($this->agencyContext->getUser());
        $this->em->flush();

        return $handover;
    }

    public function requireSession(?string $ref): PosSession
    {
        $id = $this->extractId($ref, 'session');
        $session = $this->sessions->find($id);
        if (!$session instanceof PosSession) {
            throw new UnavailableDataException('POS session not found.');
        }
        $this->agencyContext->assertOwns($session->getAgency());

        return $session;
    }

    public function requireOpenSession(?string $ref): PosSession
    {
        $session = $this->requireSession($ref);
        if (PosSession::STATUS_OPEN !== $session->getStatus()) {
            throw new UnprocessableEntityException('POS session is not open.');
        }

        return $session;
    }

    private function extractId(?string $ref, string $field = 'id'): string
    {
        $ref = trim((string) $ref);
        if ('' === $ref) {
            throw new UnprocessableEntityException(sprintf('%s is required.', $field));
        }
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));
            $ref = (string) end($parts);
        }

        return $ref;
    }
}
