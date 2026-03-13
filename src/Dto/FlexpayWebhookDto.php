<?php

namespace App\Dto;

class FlexpayWebhookDto
{
    public function __construct(
        public ?string $transactionId = null,

        public ?string $status = null,
    ) {
    }
}
