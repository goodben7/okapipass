<?php

namespace App\Dto\Traveler;

use App\Entity\TravelerBeneficiary;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTravelerBeneficiaryDto
{
    public function __construct(
        #[Assert\Length(max: 160)]
        public ?string $fullName = null,

        #[Assert\Length(max: 20)]
        public ?string $phone = null,

        #[Assert\Choice(callback: [TravelerBeneficiary::class, 'getRelationsAsList'])]
        public ?string $relation = null,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $dateOfBirth = null,

        #[Assert\Length(max: 80)]
        public ?string $idDocument = null,
    ) {
    }
}
