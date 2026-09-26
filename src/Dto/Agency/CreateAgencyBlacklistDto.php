<?php

namespace App\Dto\Agency;

use App\Entity\AgencyBlacklistEntry;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyBlacklistDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [AgencyBlacklistEntry::class, 'getTypesAsList'])]
        public ?string $type = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $value = null,

        #[Assert\Length(max: 255)]
        public ?string $reason = null,

        public ?bool $active = true,
    ) {
    }
}
