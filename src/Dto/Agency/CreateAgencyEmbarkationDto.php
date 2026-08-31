<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAgencyEmbarkationDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\NotBlank]
        public ?string $offer = null,

        #[Assert\NotBlank]
        public ?string $transport = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $departureDate = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/')]
        public ?string $departureTime = null,

        public ?string $notes = null,

        public ?string $driver = null,

        /** @var list<string>|null */
        public ?array $ticketIds = null,
    ) {
    }
}
