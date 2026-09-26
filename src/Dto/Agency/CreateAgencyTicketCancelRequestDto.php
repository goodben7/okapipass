<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyTicketCancelRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $reason = null,
    ) {
    }
}
