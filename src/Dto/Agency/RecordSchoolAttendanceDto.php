<?php

namespace App\Dto\Agency;

use App\Entity\SchoolAttendance;
use Symfony\Component\Validator\Constraints as Assert;

final class RecordSchoolAttendanceDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $contractId = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $date = null,

        #[Assert\NotBlank]
        public ?string $studentId = null,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [SchoolAttendance::class, 'getStatusesAsList'])]
        public ?string $status = null,
    ) {
    }
}
