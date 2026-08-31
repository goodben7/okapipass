<?php

namespace App\Dto\Agency;

use App\Entity\AgencyDriver;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyDriverDto
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public ?string $fullName = null,

        #[Assert\Length(max: 20)]
        public ?string $phone = null,

        #[Assert\Length(max: 40)]
        public ?string $licenseNumber = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $licenseExpiresAt = null,

        #[Assert\Choice(callback: [AgencyDriver::class, 'getStatusesAsList'])]
        public ?string $status = null,

        public ?string $notes = null,
    ) {
    }
}
