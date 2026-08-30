<?php

namespace App\Message;

use App\Event\EventMessageInterface;

final readonly class CheckAgencyPaymentStatusMessage implements EventMessageInterface
{
    public function __construct(
        private string $paymentId,
        private int $attempt = 1,
    ) {
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }
}
