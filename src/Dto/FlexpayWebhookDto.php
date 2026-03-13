<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class FlexpayWebhookDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $transactionId = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 30)]
        public ?string $status = null,
    ) {
    }
}
