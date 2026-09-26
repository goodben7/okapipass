<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateSurprisePoolDto
{
    public function __construct(
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        public ?bool $active = null,
    ) {
    }
}
