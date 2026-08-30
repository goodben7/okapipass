<?php

namespace App\Manager;

use App\ApiResource\Public\PublicAgencyBookingPaymentResource;
use App\ApiResource\Public\PublicAgencyTicketResource;
use App\Contract\AgencyFlexPayClientInterface;
use App\Domain\Agency\AgencyPricingService;
use App\Domain\Agency\AgencyTicketIssuanceService;
use App\Entity\AgencyBooking;
use App\Entity\AgencyOffer;
use App\Entity\AgencyPayment;
use App\Entity\AgencyTicket;
use App\Exception\ConflictException;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyBookingRepository;
use App\Repository\AgencyPaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PublicAgencyPaymentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyBookingRepository $bookings,
        private AgencyPaymentRepository $payments,
        private AgencyPricingService $pricing,
        private AgencyTicketIssuanceService $ticketIssuance,
        private AgencyFlexPayClientInterface $flexPay,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function initiatePayment(string $publicToken, string $method): PublicAgencyBookingPaymentResource
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

        $response = $this->flexPay->initiate($payment, (string) $booking->getPassengerPhone());
        $payment->setProviderResponse($response->raw);

        if ($response->isSuccess()) {
            $payment->setProviderTransactionId($response->transactionId);
        } else {
            $payment->setStatus(AgencyPayment::STATUS_FAILED);
            $booking->setPaymentStatus(AgencyBooking::PAYMENT_STATUS_FAILED);
        }

        $this->em->flush();

        return $this->toPaymentResource($booking, $payment);
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

        if (AgencyPayment::CHANNEL_ONLINE !== $payment->getChannel()) {
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
            $check = $this->flexPay->checkStatus((string) $transactionId);
            $providerResponse = $payment->getProviderResponse() ?? [];
            $providerResponse['check'] = $check->raw;
            $payment->setProviderResponse($providerResponse);

            $normalizedStatus = \is_string($check->status)
                ? \strtoupper(\trim($check->status))
                : $check->status;

            if ($check->isSuccess() && \in_array($normalizedStatus, ['SUCCESS', 'PAID', '0', 0], true)) {
                $this->fulfillSuccessfulPayment($payment);
            } elseif (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
                $this->markFailed($payment);
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
}
