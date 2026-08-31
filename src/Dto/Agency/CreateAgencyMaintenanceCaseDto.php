<?php

namespace App\Dto\Agency;

use App\Entity\AgencyMaintenanceCase;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyMaintenanceCaseDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $transport = null,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [AgencyMaintenanceCase::class, 'getTypesAsList'])]
        public ?string $type = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $title = null,

        public ?string $description = null,

        #[Assert\Positive]
        public ?int $odometerKm = null,

        #[Assert\PositiveOrZero]
        public ?int $estimatedCost = null,

        #[Assert\Length(max: 120)]
        public ?string $vendorName = null,
    ) {
    }
}
