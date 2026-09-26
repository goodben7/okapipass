<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class ValidateAgencyTicketQrDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public ?string $token = null,
    ) {
    }
}
