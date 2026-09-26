<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyDepotDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 40)]
        public ?string $code = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        public ?bool $active = true,
    ) {
    }
}
