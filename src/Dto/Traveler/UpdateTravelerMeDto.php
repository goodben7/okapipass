<?php

namespace App\Dto\Traveler;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTravelerMeDto
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public ?string $displayName = null,

        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $email = null,

        #[Assert\Length(max: 80)]
        public ?string $idDocument = null,

        #[Assert\Length(max: 120)]
        public ?string $emergencyContactName = null,

        #[Assert\Length(max: 20)]
        public ?string $emergencyContactPhone = null,

        /** @var array<string, mixed>|null */
        public ?array $preferences = null,
    ) {
    }
}
