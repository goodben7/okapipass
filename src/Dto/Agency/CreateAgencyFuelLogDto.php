<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyFuelLogDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $transport = null,

        public ?string $driver = null,

        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $liters = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $amount = null,

        #[Assert\Currency]
        public ?string $currency = null,

        #[Assert\PositiveOrZero]
        public ?int $odometerKm = null,

        public ?string $fueledAt = null,

        public ?string $notes = null,
    ) {
    }
}
