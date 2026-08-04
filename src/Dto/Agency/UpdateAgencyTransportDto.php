<?php

namespace App\Dto\Agency;

use App\Entity\AgencyTransport;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyTransportDto
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public ?string $label = null,

        #[Assert\Choice(callback: [AgencyTransport::class, 'getKindsAsList'])]
        public ?string $kind = null,

        #[Assert\Length(max: 30)]
        public ?string $plateNumber = null,

        #[Assert\Positive]
        public ?int $capacity = null,

        #[Assert\Choice(callback: [AgencyTransport::class, 'getStatusesAsList'])]
        public ?string $status = null,
    ) {
    }
}
