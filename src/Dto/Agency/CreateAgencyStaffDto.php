<?php

namespace App\Dto\Agency;

use App\Domain\Agency\AgencyStaffRole;
use Symfony\Component\Validator\Constraints as Assert;

class CreateAgencyStaffDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public ?string $email = null,

        #[Assert\NotBlank]
        #[Assert\Length(min: 6, max: 120)]
        public ?string $password = null,

        #[Assert\Length(max: 120)]
        public ?string $displayName = null,

        #[Assert\Length(max: 15)]
        public ?string $phone = null,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [AgencyStaffRole::class, 'all'])]
        public ?string $role = AgencyStaffRole::CASHIER,
    ) {
    }
}
