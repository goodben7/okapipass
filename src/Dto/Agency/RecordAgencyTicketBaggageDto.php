<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class RecordAgencyTicketBaggageDto
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $kg = null,
    ) {
    }
}
