<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class GenerateMonthlyPassDeclarationDto
{
    public function __construct(
        /** Period to declare, format YYYY-MM (e.g. 2026-08). */
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-(0[1-9]|1[0-2])$/', message: 'yearMonth must be YYYY-MM.')]
        public ?string $yearMonth = null,
    ) {
    }
}
