<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyParcelDto
{
    public function __construct(
        public ?string $offer = null,

        public ?string $transport = null,

        public ?string $embarkation = null,

        #[Assert\NotBlank]
        public ?string $senderName = null,

        #[Assert\NotBlank]
        public ?string $senderPhone = null,

        #[Assert\NotBlank]
        public ?string $recipientName = null,

        #[Assert\NotBlank]
        public ?string $recipientPhone = null,

        #[Assert\Positive]
        public ?float $weightKg = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $fee = null,

        #[Assert\Currency]
        public ?string $currency = null,

        public ?string $travelDate = null,

        public ?string $notes = null,
    ) {
    }
}
