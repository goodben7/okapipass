<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyObligationDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $title = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $dueDate = null,

        /** Obligation type id or code (optional). */
        public ?string $type = null,

        #[Assert\Length(max: 80)]
        public ?string $reference = null,

        #[Assert\PositiveOrZero]
        public ?int $reminderDays = null,

        public ?string $notes = null,
    ) {
    }
}
