<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class AssignTripTransportDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $transportId = null,

        public ?string $driverId = null,

        public bool $force = false,

        public ?string $reason = null,
    ) {
    }
}
