<?php

namespace App\MessageHandler;

use App\Entity\AgencyPayment;
use App\Manager\PublicAgencyPaymentManager;
use App\Message\CheckAgencyPaymentStatusMessage;
use App\Repository\AgencyPaymentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class CheckAgencyPaymentStatusMessageHandler
{
    private const int MAX_ATTEMPTS = 12;

    public function __construct(
        private AgencyPaymentRepository $payments,
        private PublicAgencyPaymentManager $manager,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CheckAgencyPaymentStatusMessage $message): void
    {
        $payment = $this->payments->find($message->getPaymentId());
        if (!$payment instanceof AgencyPayment) {
            return;
        }

        if (AgencyPayment::CHANNEL_ONLINE !== $payment->getChannel()) {
            return;
        }

        if (AgencyPayment::METHOD_MOBILE_MONEY !== $payment->getMethod()) {
            return;
        }

        if (\in_array($payment->getStatus(), [AgencyPayment::STATUS_PAID, AgencyPayment::STATUS_FAILED], true)) {
            return;
        }

        $attempt = max(1, $message->getAttempt());
        $finalized = $this->manager->refreshPaymentStatus($payment);

        if ($finalized || $attempt >= self::MAX_ATTEMPTS) {
            return;
        }

        $delayMs = min(300000, 20000 * (int) (2 ** max(0, $attempt - 1)));
        $this->logger->info('agency.payment.poll.reschedule', [
            'paymentId' => $payment->getId(),
            'attempt' => $attempt + 1,
            'delayMs' => $delayMs,
        ]);

        $this->bus->dispatch(
            new CheckAgencyPaymentStatusMessage((string) $payment->getId(), $attempt + 1),
            [new DelayStamp($delayMs)],
        );
    }
}
