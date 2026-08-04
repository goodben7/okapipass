<?php

namespace App\Dto\Agency;

use App\Domain\Agency\AgencyStaffRole;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyStaffDto
{
    public function __construct(
        #[Assert\Choice(callback: [AgencyStaffRole::class, 'all'])]
        public ?string $role = null,

        public ?bool $active = null,
    ) {
    }
}
