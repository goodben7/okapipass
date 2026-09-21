<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class RejectOntFptDeclarationDto
{
    public function __construct(
        #[Assert\Length(max: 500)]
        public ?string $reason = null,
    ) {
    }
}
