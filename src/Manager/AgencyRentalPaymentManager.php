<?php

namespace App\Manager;

use App\Contract\AgencyFlexPayClientInterface;
use App\Domain\Agency\AgencyStaffRole;
use App\Dto\Agency\CreateAgencyRentalPaymentDto;
use App\Entity\AgencyPayment;
use App\Entity\AgencyRentalContract;
use App\Exception\ConflictException;
use App\Exception\UnprocessableEntityException;
use App\Message\CheckAgencyPaymentStatusMessage;
use App\Repository\AgencyPaymentRepository;
use App\Service\Agency\AgencyContext;
use App\Service\Agency\AgencyFleetNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class AgencyRentalPaymentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private AgencyPaymentRepository $payments,
        private AgencyFlexPayClientInterface $flexPay,
        private AgencyFleetNotifier $notifier,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function initiate(AgencyRentalContract $contract, CreateAgencyRentalPaymentDto $dto): AgencyPayment
    {
        $this->agencyContext->requirePermission(AgencyStaffRole::PAYMENT_WRITE);
        $this->agencyContext->assertOwns($contract->getAgency());
        $this->assertPayable($contract);

        $existingPaid = $this->payments->findPaidForRentalContract($contract);
        if ($existingPaid instanceof AgencyPayment) {
            throw new ConflictException('Rental contract already has a paid payment.');
        }

        $open = $this->payments->findOpenForRentalContract($contract);
        if ($open instanceof AgencyPayment && $open->getMethod() !== (string) $dto->method) {
            throw new ConflictException('A payment is already in progress for this rental contract.');
        }
        if ($open instanceof AgencyPayment) {
            return $open;
        }

        $agency = $contract->getAgency();
        $amount = $this->resolveAmount($contract, $dto->amount);
        $currency = $contract->getCurrency();
        if (!$agency?->supportsCurrency($currency)) {
            throw new UnprocessableEntityException(sprintf('Currency %s is not supported by this agency.', $currency));
        }

        $method = (string) $dto->method;
        $payment = new AgencyPayment();
        $payment->setAgency($agency);
        $payment->setRentalContract($contract);
        $payment->setReference($this->nextReference($agency?->getId()));
        $payment->setAmount($amount);
        $payment->setCurrency($currency);
        $payment->setMethod($method);
        $payment->setChannel(AgencyPayment::CHANNEL_RENTAL);
        $payment->setCollectedBy($this->agencyContext->getUser());
        $payment->setNotes($dto->notes);

        if (AgencyPayment::METHOD_CASH === $method) {
            $payment->setStatus(AgencyPayment::STATUS_PAID);
            $payment->setPaidAt(new \DateTimeImmutable('now'));
            $this->em->persist($payment);
            $this->em->flush();
            $this->notifier->notifyRentalPaid($contract, $amount);

            return $payment;
        }

        $payment->setStatus(AgencyPayment::STATUS_PENDING);
        $payment->setProvider(AgencyPayment::PROVIDER_FLEXPAY);
        $this->em->persist($payment);
        $this->em->flush();

        if (AgencyPayment::METHOD_CARD === $method) {
            $payment->setProviderResponse([
                'mode' => 'HTML_FORM',
                'formUrl' => \sprintf('/api/agency/payments/%s/card/form', (string) $payment->getId()),
            ]);
            $this->em->flush();

            return $payment;
        }

        $response = $this->flexPay->initiate($payment, (string) $contract->getClientPhone());
        $payment->setProviderResponse($response->raw);

        if ($response->isSuccess()) {
            $payment->setProviderTransactionId($response->transactionId);
            $this->schedulePaymentStatusPoll($payment);
        } else {
            $payment->setStatus(AgencyPayment::STATUS_FAILED);
        }

        $this->em->flush();

        return $payment;
    }

    public function checkPayment(AgencyRentalContract $contract): AgencyPayment
    {
        $this->agencyContext->assertOwns($contract->getAgency());

        $payment = $this->payments->findOpenForRentalContract($contract)
            ?? $this->payments->findLatestForRentalContract($contract);

        if (!$payment instanceof AgencyPayment) {
            throw new UnprocessableEntityException('No payment found for this rental contract.');
        }

        $this->refreshPaymentStatus($payment);
        $this->em->refresh($payment);

        return $payment;
    }

    public function refreshPaymentStatus(AgencyPayment $payment): bool
    {
        if (AgencyPayment::CHANNEL_RENTAL !== $payment->getChannel()) {
            return false;
        }

        if (\in_array($payment->getStatus(), [AgencyPayment::STATUS_PAID, AgencyPayment::STATUS_FAILED], true)) {
            return true;
        }

        if (AgencyPayment::METHOD_CASH === $payment->getMethod()) {
            return AgencyPayment::STATUS_PAID === $payment->getStatus();
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
                $this->markPaid($payment);

                return true;
            }

            if (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
                $payment->setStatus(AgencyPayment::STATUS_FAILED);
                $this->em->flush();

                return true;
            }
        } catch (\Throwable $e) {
            $this->logger->error('agency.rental.payment.poll.exception', [
                'paymentId' => $payment->getId(),
                'transactionId' => $transactionId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        $this->em->flush();

        return false;
    }

    private function markPaid(AgencyPayment $payment): void
    {
        if (AgencyPayment::STATUS_PAID === $payment->getStatus()) {
            return;
        }

        $payment->setStatus(AgencyPayment::STATUS_PAID);
        $payment->setPaidAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        $contract = $payment->getRentalContract();
        if ($contract instanceof AgencyRentalContract) {
            $this->notifier->notifyRentalPaid($contract, $payment->getAmount());
        }
    }

    private function assertPayable(AgencyRentalContract $contract): void
    {
        if (!\in_array($contract->getStatus(), [
            AgencyRentalContract::STATUS_CONFIRMED,
            AgencyRentalContract::STATUS_ACTIVE,
        ], true)) {
            throw new UnprocessableEntityException('Only CONFIRMED or ACTIVE rental contracts can be paid.');
        }
    }

    private function resolveAmount(AgencyRentalContract $contract, ?int $amount): int
    {
        if (null !== $amount && $amount > 0) {
            return $amount;
        }

        $deposit = $contract->getDepositAmount();
        if (null !== $deposit && $deposit > 0) {
            return $deposit;
        }

        return (int) $contract->getTotalAmount();
    }

    private function schedulePaymentStatusPoll(AgencyPayment $payment): void
    {
        $this->bus->dispatch(
            new CheckAgencyPaymentStatusMessage((string) $payment->getId(), 1),
            [new DelayStamp(15000)],
        );
    }

    private function nextReference(?string $agencyId): string
    {
        return sprintf('CRP-%s-%s', substr((string) $agencyId, -4), strtoupper(bin2hex(random_bytes(3))));
    }
}
