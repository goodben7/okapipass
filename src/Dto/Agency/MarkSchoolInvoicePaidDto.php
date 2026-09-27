<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class MarkSchoolInvoicePaidDto
{
    public function __construct(
        #[Assert\Length(max: 2000)]
        public ?string $notes = null,
    ) {
    }
}
