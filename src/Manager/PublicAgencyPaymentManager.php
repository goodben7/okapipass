<?php

namespace App\Manager;

use App\ApiResource\Public\PublicAgencyBookingGroupPaymentResource;
use App\ApiResource\Public\PublicAgencyBookingPaymentResource;
use App\ApiResource\Public\PublicAgencyTicketResource;
use App\Contract\AgencyFlexPayClientInterface;
use App\Domain\Agency\AgencyPricingService;
use App\Domain\Agency\AgencyTicketIssuanceService;
use App\Entity\AgencyBooking;
use App\Entity\AgencyBookingGroup;
use App\Entity\AgencyOffer;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Message\CheckAgencyPaymentStatusMessage;
use App\Repository\AgencyBookingGroupRepository;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyPaymentRepository;
use App\Service\PublicAgency\PublicAgencyPaymentNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class PublicAgencyPaymentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyBookingRepository $bookings,
        private AgencyBookingGroupRepository $bookingGroups,
        private AgencyPaymentRepository $payments,
        private AgencyPricingService $pricing,
        private AgencyTicketIssuanceService $ticketIssuance,
        private AgencyFlexPayClientInterface $flexPay,
        private PublicAgencyPaymentNotifier $notifier,
        private AgencyRentalPaymentManager $rentalPayments,
        private MessageBusInterface $bus,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function initiatePayment(string $publicToken, string $method, ?string $payerPhone = null): PublicAgencyBookingPaymentResource
    {
        $booking = $this->requireOnlineBookingByToken($publicToken);

        if ($booking->isExpired()) {
            throw new UnprocessableEntityException('Booking hold has expired.');
        }

        $existing = $this->payments->findOpenForBooking($booking);
        if ($existing instanceof AgencyPayment) {
            if ($existing->getMethod() !== $method) {
                throw new ConflictException('A payment is already in progress for this booking.');
            }

            return $this->toPaymentResource($booking, $existing);
        }

        $booking = $this->requirePayableBooking($booking);

        $offer = $booking->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('Booking has no offer.');
        }

        $quote = $this->pricing->quote($booking->getOkapiPassRef());
        $amount = (int) $offer->getTicketPrice() + (int) $quote['passPrice'];

        $payment = new AgencyPayment();
        $payment->setAgency($booking->getAgency());
        $payment->setBooking($booking);
        $payment->setReference($this->nextReference());
        $payment->setAmount($amount);
        $payment->setCurrency($offer->getCurrency());
        $payment->setMethod($method);
        $payment->setStatus(AgencyPayment::STATUS_PENDING);
        $payment->setChannel(AgencyPayment::CHANNEL_ONLINE);
        $payment->setProvider(AgencyPayment::PROVIDER_FLEXPAY);

        $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_PENDING);

        $this->em->persist($payment);
        $this->em->flush();

        if (AgencyPayment::METHOD_CARD === $method) {
            $payment->setProviderResponse([
                'mode' => 'HTML_FORM',
                'formUrl' => \sprintf('/api/public/agency/payments/%s/card/form', (string) $payment->getId()),
            ]);
            $this->em->flush();

            return $this->toPaymentResource($booking, $payment);
        }

        $mmPhone = $this->resolveMobileMoneyPhone($payerPhone, (string) $booking->getPassengerPhone());
        $payment->setPayerPhone($mmPhone);

        $response = $this->flexPay->initiate($payment, $mmPhone);
        $payment->setProviderResponse($response->raw);

        if ($response->isSuccess()) {
            $payment->setProviderTransactionId($response->transactionId);
            $this->schedulePaymentStatusPoll($payment);
        } else {
            $payment->setStatus(AgencyPayment::STATUS_FAILED);
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_FAILED);
        }

        $this->em->flush();

        return $this->toPaymentResource($booking, $payment);
    }

    public function initiateGroupPayment(string $publicToken, string $method, ?string $payerPhone = null): PublicAgencyBookingGroupPaymentResource
    {
        $group = $this->requireOnlineGroupByToken($publicToken);

        if ($group->isExpired()) {
            throw new UnprocessableEntityException('Booking group hold has expired.');
        }

        $existing = $this->payments->findOpenForBookingGroup($group);
        if ($existing instanceof AgencyPayment) {
            if ($existing->getMethod() !== $method) {
                throw new ConflictException('A payment is already in progress for this booking group.');
            }

            return $this->toGroupPaymentResource($group, $existing);
        }

        $group = $this->requirePayableGroup($group);

        $offer = $group->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('Booking group has no offer.');
        }

        $amount = $this->computeGroupAmount($group, $offer);

        $payment = new AgencyPayment();
        $payment->setAgency($group->getAgency());
        $payment->setBookingGroup($group);
        $payment->setReference($this->nextReference());
        $payment->setAmount($amount);
        $payment->setCurrency($offer->getCurrency());
        $payment->setMethod($method);
        $payment->setStatus(AgencyPayment::STATUS_PENDING);
        $payment->setChannel(AgencyPayment::CHANNEL_ONLINE);
        $payment->setProvider(AgencyPayment::PROVIDER_FLEXPAY);

        $group->setPaymentStatus(AgencyBookingGroup::PAYMENT_STATUS_PENDING);
        $group->syncChildBookingStates();

        $this->em->persist($payment);
        $this->em->flush();

        if (AgencyPayment::METHOD_CARD === $method) {
            $payment->setProviderResponse([
                'mode' => 'HTML_FORM',
                'formUrl' => \sprintf('/api/public/agency/payments/%s/card/form', (string) $payment->getId()),
            ]);
            $this->em->flush();

            return $this->toGroupPaymentResource($group, $payment);
        }

        $defaultPhone = (string) ($group->getContactPhone() ?? $group->getBookings()->first()?->getPassengerPhone() ?? '');
        $mmPhone = $this->resolveMobileMoneyPhone($payerPhone, $defaultPhone);
        $payment->setPayerPhone($mmPhone);

        $response = $this->flexPay->initiate($payment, $mmPhone);
        $payment->setProviderResponse($response->raw);

        if ($response->isSuccess()) {
            $payment->setProviderTransactionId($response->transactionId);
            $this->schedulePaymentStatusPoll($payment);
        } else {
            $payment->setStatus(AgencyPayment::STATUS_FAILED);
            $group->setPaymentStatus(AgencyBookingGroup::PAYMENT_STATUS_FAILED);
            $group->syncChildBookingStates();
        }

        $this->em->flush();

        return $this->toGroupPaymentResource($group, $payment);
    }

    public function checkGroupPaymentByPublicToken(string $publicToken): PublicAgencyBookingGroupPaymentResource
    {
        $group = $this->requireOnlineGroupByToken($publicToken);
        $payment = $this->payments->findOpenForBookingGroup($group)
            ?? $this->payments->findLatestForBookingGroup($group);

        if (!$payment instanceof AgencyPayment) {
            throw new UnavailableDataException('No payment found for this booking group.');
        }

        $this->refreshPaymentStatus($payment);
        $this->em->refresh($group);
        $this->em->refresh($payment);

        return $this->toGroupPaymentResource($group, $payment);
    }

    public function checkPaymentByPublicToken(string $publicToken): PublicAgencyBookingPaymentResource
    {
        $booking = $this->requireOnlineBookingByToken($publicToken);
        $payment = $this->payments->findOpenForBooking($booking)
            ?? $this->payments->findLatestForBooking($booking);

        if (!$payment instanceof AgencyPayment) {
            throw new UnavailableDataException('No payment found for this booking.');
        }

        $this->refreshPaymentStatus($payment);
        $this->em->refresh($booking);
        $this->em->refresh($payment);

        return $this->toPaymentResource($booking, $payment);
    }

    /**
     * Poll FlexPay and finalize payment when possible.
     * Returns true when payment reached a terminal state (PAID or FAILED).
     */
    public function refreshPaymentStatus(AgencyPayment $payment): bool
    {
        $payment = $this->payments->find($payment->getId());
        if (!$payment instanceof AgencyPayment) {
            return false;
        }

        if (\in_array($payment->getStatus(), [AgencyPayment::STATUS_PAID, AgencyPayment::STATUS_FAILED], true)) {
            return true;
        }

        $transactionId = $payment->getProviderTransactionId();
        if (null === $transactionId || '' === trim($transactionId)) {
            return false;
        }

        try {
            $check = $this->flexPay->checkStatus((string) $transactionId);
            $providerResponse = $payment->getProviderResponse() ?? [];
            $providerResponse['poll'] = $check->raw;
            $payment->setProviderResponse($providerResponse);

            $normalizedStatus = \is_string($check->status)
                ? \strtoupper(\trim($check->status))
                : $check->status;

            if ($check->isSuccess() && \in_array($normalizedStatus, ['SUCCESS', 'PAID', '0', 0], true)) {
                $hadTicket = null !== $payment->getTicket();
                $hadGroupTickets = $this->groupHasTickets($payment);
                if ($payment->getBookingGroup() instanceof AgencyBookingGroup) {
                    $ticket = $this->fulfillSuccessfulGroupPayment($payment);
                    if (!$hadGroupTickets) {
                        $this->notifier->notifyPaid($payment, $ticket);
                    }
                } else {
                    $ticket = $this->fulfillSuccessfulPayment($payment);
                    if (!$hadTicket) {
                        $this->notifier->notifyPaid($payment, $ticket);
                    }
                }

                return true;
            }

            if (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
                $this->markFailed($payment);
                $this->em->flush();

                return true;
            }
        } catch (\Throwable $e) {
            $this->logger->error('agency.payment.poll.exception', [
                'paymentId' => $payment->getId(),
                'transactionId' => $transactionId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        $this->em->flush();

        return false;
    }

    public function getTicketByPublicToken(string $publicToken): PublicAgencyTicketResource
    {
        $booking = $this->requireOnlineBookingByToken($publicToken);
        $ticket = $booking->getTicket();
        if (!$ticket instanceof AgencyTicket) {
            throw new UnavailableDataException('Ticket not available yet.');
        }

        return $this->toTicketResource($booking, $ticket);
    }

    public function handleFlexpayWebhook(): ?AgencyPayment
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        $payload = $this->parseWebhookPayload($request);
        if (null === $payload) {
            return null;
        }

        $transactionId = $this->extractTransactionId($payload);
        $reference = $this->extractReference($payload);

        $payment = $this->resolvePayment($transactionId, $reference);
        if (!$payment instanceof AgencyPayment) {
            return null;
        }

        if (AgencyPayment::CHANNEL_ONLINE !== $payment->getChannel()
            && AgencyPayment::CHANNEL_RENTAL !== $payment->getChannel()) {
            return null;
        }

        if (null !== $transactionId && '' !== \trim((string) $transactionId) && null === $payment->getProviderTransactionId()) {
            $payment->setProviderTransactionId((string) $transactionId);
        }

        $providerResponse = $payment->getProviderResponse() ?? [];
        $providerResponse['webhook'] = $payload;
        $payment->setProviderResponse($providerResponse);

        if (AgencyPayment::STATUS_PAID === $payment->getStatus()) {
            $this->em->flush();

            return $payment;
        }

        $incomingStatus = $this->normalizeIncomingStatus($payload);
        if (\in_array($incomingStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR'], true)) {
            $this->markFailed($payment);

            $this->em->flush();

            return $payment;
        }

        if (null === $transactionId || '' === \trim((string) $transactionId)) {
            $this->em->flush();

            return $payment;
        }

        try {
            if (null !== $transactionId && '' !== \trim((string) $transactionId)) {
                if (AgencyPayment::CHANNEL_RENTAL === $payment->getChannel()) {
                    $this->rentalPayments->refreshPaymentStatus($payment);
                } else {
                    $this->refreshPaymentStatus($payment);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('agency.flexpay.webhook.check_status.exception', [
                'paymentId' => $payment->getId(),
                'transactionId' => $transactionId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        $this->em->flush();

        return $payment;
    }

    public function fulfillSuccessfulPayment(AgencyPayment $payment): AgencyTicket
    {
        $payment = $this->payments->find($payment->getId());
        if (!$payment instanceof AgencyPayment) {
            throw new UnprocessableEntityException('Payment not found.');
        }

        if (AgencyPayment::STATUS_PAID === $payment->getStatus() && null !== $payment->getTicket()) {
            return $payment->getTicket();
        }

        $booking = $payment->getBooking();
        if (!$booking instanceof AgencyBooking) {
            throw new UnprocessableEntityException('Payment has no booking.');
        }

        $booking = $this->bookings->find($booking->getId());
        if (!$booking instanceof AgencyBooking) {
            throw new UnprocessableEntityException('Booking not found.');
        }

        if ($booking->isCancelled() || $booking->isExpired()) {
            throw new UnprocessableEntityException('Booking is no longer valid.');
        }

        $now = new \DateTimeImmutable();
        $payment->setStatus(AgencyPayment::STATUS_PAID);
        $payment->setPaidAt($now);

        $ticket = $this->ticketIssuance->issueFromBooking($booking, sendSms: true);
        $payment->setTicket($ticket);
        $this->em->flush();

        return $ticket;
    }

    public function fulfillSuccessfulGroupPayment(AgencyPayment $payment): AgencyTicket
    {
        $payment = $this->payments->find($payment->getId());
        if (!$payment instanceof AgencyPayment) {
            throw new UnprocessableEntityException('Payment not found.');
        }

        $group = $payment->getBookingGroup();
        if (!$group instanceof AgencyBookingGroup) {
            throw new UnprocessableEntityException('Payment has no booking group.');
        }

        $group = $this->bookingGroups->find($group->getId());
        if (!$group instanceof AgencyBookingGroup) {
            throw new UnprocessableEntityException('Booking group not found.');
        }

        $existingTicket = $group->getTicket();
        if (AgencyPayment::STATUS_PAID === $payment->getStatus() && $existingTicket instanceof AgencyTicket) {
            return $existingTicket;
        }

        if ($group->isCancelled() || $group->isExpired()) {
            throw new UnprocessableEntityException('Booking group is no longer valid.');
        }

        $now = new \DateTimeImmutable();
        $payment->setStatus(AgencyPayment::STATUS_PAID);
        $payment->setPaidAt($now);

        $ticket = $this->ticketIssuance->issueFromGroup($group, sendSms: true);
        $payment->setTicket($ticket);
        $this->em->flush();

        return $ticket;
    }

    private function requirePayableBooking(AgencyBooking $booking): AgencyBooking
    {
        if (AgencyBooking::STATUS_PENDING !== $booking->getStatus()) {
            if (AgencyBooking::STATUS_CONFIRMED === $booking->getStatus() && null !== $booking->getTicket()) {
                throw new ConflictException('Booking is already paid.');
            }

            throw new UnprocessableEntityException('Booking is not payable.');
        }

        if (!\in_array($booking->getPaymentStatus(), [
            AgencyBooking::PAYMENT_STATUS_UNPAID,
            AgencyBooking::PAYMENT_STATUS_FAILED,
        ], true)) {
            if (AgencyBooking::PAYMENT_STATUS_PAID === $booking->getPaymentStatus()) {
                throw new ConflictException('Booking is already paid.');
            }

            throw new ConflictException('A payment is already in progress for this booking.');
        }

        return $booking;
    }

    private function requireOnlineBookingByToken(string $publicToken): AgencyBooking
    {
        $token = trim($publicToken);
        if ('' === $token) {
            throw new UnavailableDataException('Booking not found.');
        }

        $booking = $this->bookings->findOneByPublicToken($token);
        if (!$booking instanceof AgencyBooking || AgencyBooking::CHANNEL_ONLINE !== $booking->getChannel()) {
            throw new UnavailableDataException('Booking not found.');
        }

        return $booking;
    }

    private function toPaymentResource(AgencyBooking $booking, AgencyPayment $payment): PublicAgencyBookingPaymentResource
    {
        $cardFormUrl = null;
        if (AgencyPayment::METHOD_CARD === $payment->getMethod()) {
            $cardFormUrl = \sprintf('/api/public/agency/payments/%s/card/form', (string) $payment->getId());
        }

        return new PublicAgencyBookingPaymentResource(
            publicToken: (string) $booking->getPublicToken(),
            bookingId: (string) $booking->getId(),
            paymentId: (string) $payment->getId(),
            paymentStatus: $payment->getStatus(),
            paymentMethod: $payment->getMethod(),
            amount: $payment->getAmount(),
            currency: $payment->getCurrency(),
            providerTransactionId: $payment->getProviderTransactionId(),
            cardFormUrl: $cardFormUrl,
            ticketReference: $booking->getTicket()?->getReference(),
            bookingStatus: $booking->getStatus(),
            bookingPaymentStatus: $booking->getPaymentStatus(),
        );
    }

    private function toTicketResource(AgencyBooking $booking, AgencyTicket $ticket): PublicAgencyTicketResource
    {
        $offer = $booking->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new \LogicException('Booking has no offer.');
        }

        return new PublicAgencyTicketResource(
            publicToken: (string) $booking->getPublicToken(),
            ticketId: (string) $ticket->getId(),
            reference: (string) $ticket->getReference(),
            status: $ticket->getStatus(),
            passengerName: (string) $ticket->getPassengerName(),
            passengerPhone: (string) $ticket->getPassengerPhone(),
            seatNumber: (string) $ticket->getSeatNumber(),
            travelDate: $ticket->getTravelDate()?->format('Y-m-d') ?? '',
            ticketPrice: (int) $ticket->getTicketPrice(),
            passPrice: (int) $ticket->getPassPrice(),
            currency: (string) $ticket->getCurrency(),
            hasExistingPass: $ticket->hasExistingPass(),
            qrPayload: $ticket->getQrPayload(),
            offer: [
                'id' => (string) $offer->getId(),
                'label' => (string) $offer->getLabel(),
                'origin' => (string) $offer->getOrigin(),
                'destination' => (string) $offer->getDestination(),
                'departureTime' => (string) $offer->getDepartureTime(),
            ],
            pdfUrl: \sprintf('/api/public/agency/bookings/%s/ticket/pdf', $booking->getPublicToken()),
        );
    }

    private function markFailed(AgencyPayment $payment): void
    {
        if (AgencyPayment::STATUS_PAID !== $payment->getStatus()) {
            $payment->setStatus(AgencyPayment::STATUS_FAILED);
        }

        $booking = $payment->getBooking();
        if ($booking instanceof AgencyBooking && AgencyBooking::PAYMENT_STATUS_PAID !== $booking->getPaymentStatus()) {
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_FAILED);
        }

        $group = $payment->getBookingGroup();
        if ($group instanceof AgencyBookingGroup && AgencyBookingGroup::PAYMENT_STATUS_PAID !== $group->getPaymentStatus()) {
            $group->setPaymentStatus(AgencyBookingGroup::PAYMENT_STATUS_FAILED);
            $group->syncChildBookingStates();
        }
    }

    private function nextReference(): string
    {
        return sprintf('ABP-%s', strtoupper(bin2hex(random_bytes(4))));
    }

    /** @return array<string, mixed>|null */
    private function parseWebhookPayload(Request $request): ?array
    {
        $rawBody = $request->getContent();
        $payload = \json_decode($rawBody, true);

        if (!\is_array($payload)) {
            $payload = $request->request->all();
        }

        return \is_array($payload) && [] !== $payload ? $payload : null;
    }

    /** @param array<string, mixed> $payload */
    private function extractTransactionId(array $payload): ?string
    {
        $transactionId = $payload['transactionId']
            ?? $payload['orderNumber']
            ?? $payload['order_number']
            ?? ($payload['transaction']['orderNumber'] ?? null)
            ?? ($payload['transaction']['order_number'] ?? null);

        return null !== $transactionId ? (string) $transactionId : null;
    }

    /** @param array<string, mixed> $payload */
    private function extractReference(array $payload): ?string
    {
        $reference = $payload['reference']
            ?? ($payload['transaction']['reference'] ?? null);

        return null !== $reference ? (string) $reference : null;
    }

    private function resolvePayment(?string $transactionId, ?string $reference): ?AgencyPayment
    {
        if (null !== $transactionId && '' !== \trim($transactionId)) {
            $payment = $this->payments->findOneByProviderTransactionId($transactionId);
            if ($payment instanceof AgencyPayment) {
                return $payment;
            }
        }

        if (null !== $reference && '' !== \trim($reference)) {
            $refString = trim($reference);
            if (\str_starts_with($refString, 'ABP-')) {
                $paymentId = \substr($refString, 4);
                if ('' !== $paymentId) {
                    $payment = $this->payments->find($paymentId);
                    if ($payment instanceof AgencyPayment) {
                        return $payment;
                    }
                }
            }

            $payment = $this->payments->findOneBy(['reference' => $refString]);
            if ($payment instanceof AgencyPayment) {
                return $payment;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function normalizeIncomingStatus(array $payload): ?string
    {
        $incomingStatus = $payload['status']
            ?? ($payload['transaction']['status'] ?? null)
            ?? ($payload['code'] ?? null)
            ?? ($payload['message'] ?? null);

        if (\is_string($incomingStatus)) {
            return \strtoupper(\trim($incomingStatus));
        }

        if (\is_int($incomingStatus)) {
            return (string) $incomingStatus;
        }

        return null;
    }

    private function schedulePaymentStatusPoll(AgencyPayment $payment): void
    {
        $paymentId = (string) $payment->getId();
        if ('' === $paymentId) {
            return;
        }

        $this->bus->dispatch(
            new CheckAgencyPaymentStatusMessage($paymentId),
            [new DelayStamp(20000)],
        );
    }

    private function resolveMobileMoneyPhone(?string $payerPhone, string $passengerPhone): string
    {
        $phone = trim((string) ($payerPhone ?? ''));
        if ('' === $phone) {
            $phone = trim($passengerPhone);
        }

        if ('' === $phone) {
            throw new UnprocessableEntityException('A Mobile Money phone number is required.');
        }

        return $phone;
    }

    private function requireOnlineGroupByToken(string $publicToken): AgencyBookingGroup
    {
        $token = trim($publicToken);
        if ('' === $token) {
            throw new UnavailableDataException('Booking group not found.');
        }

        $group = $this->bookingGroups->findOneByPublicToken($token);
        if (!$group instanceof AgencyBookingGroup) {
            throw new UnavailableDataException('Booking group not found.');
        }

        return $group;
    }

    private function requirePayableGroup(AgencyBookingGroup $group): AgencyBookingGroup
    {
        if (AgencyBookingGroup::STATUS_PENDING !== $group->getStatus()) {
            if (AgencyBookingGroup::STATUS_CONFIRMED === $group->getStatus() && null !== $group->getTicket()) {
                throw new ConflictException('Booking group is already paid.');
            }

            throw new UnprocessableEntityException('Booking group is not payable.');
        }

        if (!\in_array($group->getPaymentStatus(), [
            AgencyBookingGroup::PAYMENT_STATUS_UNPAID,
            AgencyBookingGroup::PAYMENT_STATUS_FAILED,
        ], true)) {
            if (AgencyBookingGroup::PAYMENT_STATUS_PAID === $group->getPaymentStatus()) {
                throw new ConflictException('Booking group is already paid.');
            }

            throw new ConflictException('A payment is already in progress for this booking group.');
        }

        return $group;
    }

    private function computeGroupAmount(AgencyBookingGroup $group, AgencyOffer $offer): int
    {
        $amount = 0;
        foreach ($group->getBookings() as $booking) {
            $quote = $this->pricing->quote($booking->getOkapiPassRef());
            $amount += (int) $offer->getTicketPrice() + (int) $quote['passPrice'];
        }

        return $amount;
    }

    private function toGroupPaymentResource(AgencyBookingGroup $group, AgencyPayment $payment): PublicAgencyBookingGroupPaymentResource
    {
        $cardFormUrl = null;
        if (AgencyPayment::METHOD_CARD === $payment->getMethod()) {
            $cardFormUrl = \sprintf('/api/public/agency/payments/%s/card/form', (string) $payment->getId());
        }

        $ticketReferences = [];
        $groupTicket = $group->getTicket();
        if ($groupTicket instanceof AgencyTicket && null !== $groupTicket->getReference()) {
            $ticketReferences[] = (string) $groupTicket->getReference();
        }

        return new PublicAgencyBookingGroupPaymentResource(
            publicToken: (string) $group->getPublicToken(),
            groupId: (string) $group->getId(),
            groupName: (string) $group->getGroupName(),
            paymentId: (string) $payment->getId(),
            paymentStatus: $payment->getStatus(),
            paymentMethod: $payment->getMethod(),
            amount: $payment->getAmount(),
            currency: $payment->getCurrency(),
            passengerCount: $group->getBookings()->count(),
            providerTransactionId: $payment->getProviderTransactionId(),
            cardFormUrl: $cardFormUrl,
            ticketReferences: $ticketReferences,
            groupStatus: $group->getStatus(),
            groupPaymentStatus: $group->getPaymentStatus(),
        );
    }

    private function groupHasTickets(AgencyPayment $payment): bool
    {
        $group = $payment->getBookingGroup();

        return $group instanceof AgencyBookingGroup && $group->getTicket() instanceof AgencyTicket;
    }
}
