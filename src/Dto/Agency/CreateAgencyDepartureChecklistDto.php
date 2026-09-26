<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyDepartureChecklistDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $transport = null,

        public ?string $driver = null,

        public ?string $offer = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $travelDate = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $odometerKm = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 100)]
        public ?int $fuelLevelPercent = null,

        public ?bool $vehicleOk = true,

        public ?string $notes = null,
    ) {
    }
}
