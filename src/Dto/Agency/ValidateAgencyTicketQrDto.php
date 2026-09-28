<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class ValidateAgencyTicketQrDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 4096)]
        public ?string $token = null,

        public ?float $lat = null,

        public ?float $lng = null,
    ) {
    }
}
