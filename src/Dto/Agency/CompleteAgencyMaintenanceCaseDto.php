<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CompleteAgencyMaintenanceCaseDto
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public ?int $actualCost = null,

        public ?string $description = null,
    ) {
    }
}
