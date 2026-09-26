<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyFleetIncidentDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $transport = null,

        public ?string $driver = null,

        #[Assert\NotBlank]
        public ?string $type = null,

        #[Assert\NotBlank]
        public ?string $severity = null,

        public ?float $lat = null,

        public ?float $lng = null,

        public ?string $photoUrl = null,

        public ?string $notes = null,

        public ?string $occurredAt = null,
    ) {
    }
}
