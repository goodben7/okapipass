<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateCashHandoverDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $session = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $declaredAmount = null,

        #[Assert\Length(exactly: 3)]
        public ?string $currency = null,

        public ?string $notes = null,
    ) {
    }
}
