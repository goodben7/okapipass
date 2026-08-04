<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

class CreatePassDeclarationDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        /** @var list<array<string, mixed>> */
        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        public ?array $lines = null,

        public ?string $currency = null,
    ) {
    }
}
