<?php

namespace App\Dto\Agency;

use App\Entity\AgencyMaintenanceCase;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyMaintenanceCaseDto
{
    public function __construct(
        #[Assert\Choice(callback: [AgencyMaintenanceCase::class, 'getTypesAsList'])]
        public ?string $type = null,

        #[Assert\Length(max: 160)]
        public ?string $title = null,

        public ?string $description = null,

        #[Assert\Positive]
        public ?int $odometerKm = null,

        #[Assert\PositiveOrZero]
        public ?int $estimatedCost = null,

        #[Assert\PositiveOrZero]
        public ?int $actualCost = null,

        #[Assert\Length(max: 120)]
        public ?string $vendorName = null,

        #[Assert\Choice(callback: [AgencyMaintenanceCase::class, 'getStatusesAsList'])]
        public ?string $status = null,
    ) {
    }
}
