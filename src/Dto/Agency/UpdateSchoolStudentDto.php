<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateSchoolStudentDto
{
    public function __construct(
        public ?string $contract = null,

        #[Assert\Length(max: 160)]
        public ?string $fullName = null,

        #[Assert\Length(max: 20)]
        public ?string $phone = null,

        #[Assert\Length(max: 40)]
        public ?string $grade = null,

        #[Assert\Length(max: 40)]
        public ?string $pickupStopCode = null,

        #[Assert\Length(max: 40)]
        public ?string $dropoffStopCode = null,

        public ?bool $active = null,

        #[Assert\Length(max: 64)]
        public ?string $externalRef = null,
    ) {
    }
}
