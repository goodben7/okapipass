<?php

namespace App\Dto\Agency;

use App\Entity\AgencyObligation;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyObligationDto
{
    public function __construct(
        #[Assert\Length(max: 160)]
        public ?string $title = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $dueDate = null,

        public ?string $type = null,

        #[Assert\Length(max: 80)]
        public ?string $reference = null,

        #[Assert\PositiveOrZero]
        public ?int $reminderDays = null,

        public ?string $notes = null,

        #[Assert\Choice(callback: [AgencyObligation::class, 'getStatusesAsList'])]
        public ?string $status = null,
    ) {
    }
}
