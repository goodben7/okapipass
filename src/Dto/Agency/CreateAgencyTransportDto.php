<?php

namespace App\Dto\Agency;

use App\Entity\AgencyTransport;
use Symfony\Component\Validator\Constraints as Assert;

class CreateAgencyTransportDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $label = null,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [AgencyTransport::class, 'getKindsAsList'])]
        public ?string $kind = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 30)]
        public ?string $plateNumber = null,

        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $capacity = null,

        #[Assert\Choice(callback: [AgencyTransport::class, 'getStatusesAsList'])]
        public ?string $status = AgencyTransport::STATUS_ACTIVE,

        #[Assert\PositiveOrZero]
        public ?int $nextServiceKm = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $nextServiceDate = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $insuranceExpiresAt = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $technicalControlExpiresAt = null,
    ) {
    }
}
