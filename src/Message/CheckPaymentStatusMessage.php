<?php

namespace App\Message;

use App\Event\EventMessageInterface;

final readonly class CheckPaymentStatusMessage implements EventMessageInterface
{
    public function __construct(
        private string $paymentId,
        private string $conversationPhone,
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

    public function getConversationPhone(): string
    {
        return $this->conversationPhone;
    }
}
