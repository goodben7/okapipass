<?php

namespace App\Dto;

use App\Entity\Payment;
use App\Entity\Ticket;
use Symfony\Component\Validator\Constraints as Assert;

class CreatePaymentDto
{
    public function __construct(
        public ?string $reference = null,

        #[Assert\NotNull]
        public ?Ticket $ticket = null,

        #[Assert\NotNull]
        public ?string $amount = null,

        #[Assert\NotBlank]
        #[Assert\Currency]
        public ?string $currency = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 30)]
        #[Assert\Choice(callback: [Payment::class, 'getMethodsAsList'])]
        public ?string $method = null,
    ) {
    }
}
