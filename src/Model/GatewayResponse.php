<?php

namespace App\Model;

class GatewayResponse
{
    public function __construct(
        public bool $success,
        public ?string $transactionId = null,
        public ?string $status = null,
        public ?string $message = null,
        public ?array $raw = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
